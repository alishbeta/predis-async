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
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Defines a connection object used to communicate asynchronously with Redis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface ConnectionInterface
{
    /**
     * Opens the connection to Redis.
     *
     * @return PromiseInterface
     */
    public function connect();

    /**
     * Closes the connection to Redis.
     *
     * @return PromiseInterface
     */
    public function disconnect();

    /**
     * Checks if the connection to Redis is considered open.
     *
     * @return bool
     */
    public function isConnected();

    /**
     * Returns the underlying resource used to communicate with Redis.
     *
     * @return mixed
     */
    public function getResource();

    /**
     * Returns the parameters used to initialize the connection.
     *
     * @return ParametersInterface
     */
    public function getParameters();

    /**
     * Returns the underlying event loop.
     *
     * @return LoopInterface
     */
    public function getEventLoop();

    /**
     * Writes a request for the given command over the connection and reads back
     * the response returned by Redis firing the user-provided callback.
     *
     * @param CommandInterface $command Redis command.
     * @return PromiseInterface
     */
    public function executeCommand(CommandInterface $command);

    /**
     * Writes the buffer to a writable network streams.
     *
     * @return mixed
     */
    public function write();

    /**
     * Reads responses from a readable network streams.
     *
     * @return mixed
     */
    public function read();

    /**
     * Returns a string representation of the connection.
     *
     * @return string
     */
    public function __toString();
}
