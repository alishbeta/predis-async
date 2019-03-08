<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Connection;

use Predis\Command\CommandInterface;
use Predis\Connection\ParametersInterface;
use Predis\Response\Error;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use function React\Promise\Timer\timeout;
use React\Promise\Timer\TimeoutException;
use SplQueue;

/**
 * Base class providing the common logic used by to communicate asynchronously
 * with Redis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
abstract class AbstractConnection implements ConnectionInterface
{
    protected $loop;
    protected $parameters;
    protected $stream;
    protected $buffer;
    protected $commands;
    protected $state;
    protected $timeout = null;
    protected $readableCallback = null;
    protected $writableCallback = null;
    protected $lastError = null;

    /**
     * @param LoopInterface $loop Event loop instance.
     * @param ParametersInterface $parameters Initialization parameters for the connection.
     */
    public function __construct(LoopInterface $loop, ParametersInterface $parameters)
    {
        $this->loop = $loop;
        $this->parameters = $parameters;

        $this->buffer = new Buffer\StringBuffer();
        $this->commands = new SplQueue();
        $this->readableCallback = [$this, 'read'];
        $this->writableCallback = [$this, 'write'];

        $this->state = new State();
        $this->state->setProcessCallback($this->getProcessCallback());
    }

    /**
     * Returns the callback used to handle commands and firing the appropriate
     * callbacks depending on the state of the connection.
     *
     * @return mixed
     */
    protected function getProcessCallback()
    {
        return function (State $state, $response) {
            /**
             * @var CommandInterface $command
             * @var Deferred $deferred
             */
            [$command, $deferred] = $this->commands->dequeue();

            switch ($command->getId()) {
                case 'SUBSCRIBE':
                case 'PSUBSCRIBE':
                    $wrapper = $this->getStreamingWrapperCreator();
                    $callback = $wrapper($this, $deferred);
                    $state->setStreamingContext(State::PUBSUB, $callback);
                    break;

                case 'MONITOR':
                    $wrapper = $this->getStreamingWrapperCreator();
                    $callback = $wrapper($this, $deferred);
                    $state->setStreamingContext(State::MONITOR, $callback);
                    break;

                case 'MULTI':
                    $state->setState(State::MULTIEXEC);
                    goto process;

                case 'EXEC':
                case 'DISCARD':
                    $state->setState(State::CONNECTED);
                    goto process;

                default:
                    process:
                    if ($response instanceof Error) {
                        $deferred->reject($response);
                    } else {
                        $deferred->resolve($response);
                    }
                    break;
            }
        };
    }

    /**
     * Returns a wrapper to the user-provided callback used to handle response
     * chunks streamed by replies to commands such as MONITOR, SUBSCRIBE, etc.
     *
     * @return mixed
     */
    protected function getStreamingWrapperCreator()
    {
        return function ($connection, $callback) {
            return function ($state, $response) use ($connection, $callback) {
                call_user_func($callback, $response, $connection, null);
            };
        };
    }

    /**
     * Disconnects from the server and destroys the underlying resource when
     * PHP's garbage collector kicks in.
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return isset($this->stream) && stream_socket_get_name($this->stream, true) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->loop->futureTick(function () use ($deferred) {
            if (isset($this->stream)) {
                $this->loop->removeReadStream($this->stream);
                $this->loop->removeWriteStream($this->stream);
                $this->state->setState(State::DISCONNECTED);
                $this->buffer->reset();

                unset($this->stream);
            }

            $deferred->resolve();
        });

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if (!$this->isConnected()) {
            return $this->createResource();
        }

        return new FulfilledPromise($this);
    }

    /**
     * Creates the underlying resource used to communicate with Redis.
     *
     * @return PromiseInterface
     * @throws \Exception
     */
    protected function createResource()
    {
        $parameters = $this->parameters;
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;

        if ($parameters->scheme === 'unix') {
            $uri = "unix://$parameters->path";
        } else {
            $uri = "$parameters->scheme://$parameters->host:$parameters->port";
        }

        if (!$stream = @stream_socket_client($uri, $errno, $errstr, 0, $flags)) {
            return $this->onError(new ConnectionException($this, trim($errstr), $errno));
        }

        stream_set_blocking($stream, 0);

        $this->state->setState(State::CONNECTING);

        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->loop->addWriteStream($stream, function ($stream) use ($deferred) {
            $this->onConnect()->then(function($result) use ($deferred) {
                $deferred->resolve($this);
                $this->write();
            }, function ($e) use($deferred) {
                $deferred->reject($e);
            });
        });

        $this->stream = $stream;

        return timeout($promise, $parameters->timeout ?: 5, $this->loop)
            ->otherwise(function($e) {
                if ($e instanceof TimeoutException) {
                    return $this->onError(new ConnectionException($this, 'Connection timed out'));
                }

                throw $e;
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function onError(\Exception $exception)
    {
        $this->lastError = $exception;
        return $this->disconnect()->always(function() use ($exception) {
            throw $exception;
        });
    }

    public function onConnect()
    {
        $stream = $this->getResource();

        // The following code is a terrible hack but it seems to be the only way
        // to detect connection refused errors with PHP's stream sockets. You
        // should blame PHP for this, as usual.
        if (stream_socket_get_name($stream, true) === false) {
            return $this->onError(new ConnectionException($this, "Connection refused"));
        }

        $this->lastError = null;
        $this->state->setState(State::CONNECTED);

        if ($this->buffer->isEmpty()) {
            $this->loop->removeWriteStream($stream);
            $this->loop->addReadStream($stream, $this->readableCallback);
        }

        return \React\Promise\resolve(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        if (isset($this->stream)) {
            return $this->stream;
        }

        $this->createResource();

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function write()
    {
        $stream = $this->getResource();

        if ($this->buffer->isEmpty()) {
            $this->loop->removeWriteStream($stream);

            return;
        }

        $buffer = $this->buffer->read(4096);

        if (-1 === $ret = @stream_socket_sendto($stream, $buffer)) {
            return $this->onError(new ConnectionException($this, 'Error while writing bytes to the server'));
        }

        $this->buffer->discard(min($ret, strlen($buffer)));
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventLoop()
    {
        return $this->loop;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $buffer = stream_socket_recvfrom($this->getResource(), 4096);

        if ($buffer === false || $buffer === '') {
            return $this->onError(new ConnectionException($this, 'Error while reading bytes from the server'));
        }

        $this->parseResponseBuffer($buffer);
    }

    /**
     * Parses the incoming buffer and emits response objects when the buffer
     * contains one or more response payloads available for consumption.
     *
     * @param string $buffer Buffer read from the network stream.
     */
    abstract public function parseResponseBuffer($buffer);

    /**
     * {@inheritdoc}
     */
    abstract public function executeCommand(CommandInterface $command);

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getIdentifier();
    }

    /**
     * Returns the identifier for the connection.
     *
     * @return string
     */
    protected function getIdentifier()
    {
        if ($this->parameters->scheme === 'unix') {
            return $this->parameters->path;
        }

        return "{$this->parameters->host}:{$this->parameters->port}";
    }
}
