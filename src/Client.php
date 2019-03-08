<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async;

use Predis\Async\Connection\ConnectionInterface;
use Predis\Async\Connection\PhpiredisStreamConnection;
use Predis\Async\Connection\StreamConnection;
use Predis\ClientException;
use Predis\Command\CommandInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\Parameters;
use Predis\Connection\ParametersInterface;
use Predis\NotSupportedException;
use Predis\Response\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class Client implements ClientInterface
{
    const VERSION = '0.3.0-dev';
    protected $connection;
    private $profile;
    private $options;

    /**
     * @param mixed $parameters Connection parameters.
     * @param mixed $options Options to configure some behaviours of the client.
     */
    public function __construct($parameters = null, $options = null)
    {
        $this->options = $this->createOptions($options ?: []);
        $this->connection = $this->createConnection($parameters, $this->options);
        $this->profile = $this->options->profile;
    }

    /**
     * Creates an instance of Predis\Async\Configuration\Options from different
     * types of arguments or simply returns the passed argument if it is an
     * instance of Predis\Configuration\OptionsInterface.
     *
     * @param mixed $options Client options.
     *
     * @return OptionsInterface
     */
    protected function createOptions($options)
    {
        if (is_array($options)) {
            return new Configuration\Options($options);
        }

        if ($options instanceof LoopInterface) {
            return new Configuration\Options(['eventloop' => $options]);
        }

        if ($options instanceof OptionsInterface) {
            return $options;
        }

        throw new \InvalidArgumentException('Invalid type for client options');
    }

    /**
     * Initializes a connection from various types of arguments or returns the
     * passed object if it implements Predis\Connection\ConnectionInterface.
     *
     * @param mixed $parameters Connection parameters or instance.
     * @param OptionsInterface $options Client options.
     *
     * @return ConnectionInterface
     */
    protected function createConnection($parameters, OptionsInterface $options)
    {
        if ($parameters instanceof ConnectionInterface) {
            if ($parameters->getEventLoop() !== $this->options->eventloop) {
                throw new ClientException('Client and connection must share the same event loop.');
            }

            return $parameters;
        }

        $eventloop = $this->options->eventloop;
        $parameters = $this->createParameters($parameters);

        if ($options->phpiredis) {
            $connection = new PhpiredisStreamConnection($eventloop, $parameters);
        } else {
            $connection = new StreamConnection($eventloop, $parameters);
        }

        return $connection;
    }

    /**
     * Creates an instance of connection parameters.
     *
     * @param mixed $parameters Connection parameters.
     *
     * @return ParametersInterface
     */
    protected function createParameters($parameters)
    {
        if ($parameters instanceof ParametersInterface) {
            return $parameters;
        }

        return Parameters::create($parameters);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function getEventLoop()
    {
        return $this->options->eventloop;
    }

    public function connect()
    {
        return $this->connection->connect()->then(function ($connection) {
            return $this;
        });
    }

    public function disconnect()
    {
        return $this->connection->disconnect();
    }

    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Creates a Redis command with the specified arguments and sends a request
     * to the server.
     *
     * @param string $method Command ID.
     * @param array $arguments Arguments for the command (optional callback as last argument).
     *
     * @return PromiseInterface
     */
    public function __call($method, $arguments)
    {
        // \Log::warning("[RedisAsync]: {$method}", ['params' => $arguments]);
        return $this->executeCommand($this->createCommand($method, $arguments));
    }

    public function executeCommand(CommandInterface $command)
    {
        return $this->connection->executeCommand($command)
            ->then(function ($response) use ($command) {
                if ($command && !$response instanceof ResponseInterface) {
                    $response = $command->parseResponse($response);
                }
                return $response;
            });
    }

    public function createCommand($method, $arguments = [])
    {
        return $this->profile->createCommand($method, $arguments);
    }

    public function transaction(/* arguments */)
    {
        return new Transaction\MultiExec($this);
    }

    public function monitor(callable $callback, $autostart = true)
    {
        $monitor = new Monitor\Consumer($this, $callback);

        if ($autostart) {
            $monitor->start();
        }

        return $monitor;
    }

    public function pubSubLoop($channels, callable $callback)
    {
        $pubsub = new PubSub\Consumer($this, $callback);

        if (is_string($channels)) {
            $channels = ['subscribe' => [$channels]];
        }

        if (isset($channels['subscribe'])) {
            $pubsub->subscribe($channels['subscribe']);
        }

        if (isset($channels['psubscribe'])) {
            $pubsub->psubscribe($channels['psubscribe']);
        }

        return $pubsub;
    }

    public function pipeline(/* arguments */)
    {
        throw new NotSupportedException('Not yet implemented');
    }
}
