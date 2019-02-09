<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Transaction;

use Predis\Async\Client;
use Predis\Response\ResponseInterface;
use Predis\Response\Status as StatusResponse;
use React\Promise\PromiseInterface;
use RuntimeException;
use SplQueue;

/**
 * Client-side abstraction of a Redis transaction based on MULTI / EXEC.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class MultiExec
{
    protected $client;
    private $commands;

    /**
     * @param Client $client Client instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->commands = new SplQueue();

        $this->initialize();
    }

    /**
     * Initializes the transaction context.
     */
    protected function initialize()
    {
        $command = $this->client->createCommand('MULTI');

        $this->client->executeCommand($command)->then(function ($response) {
            if (false === $response) {
                throw new RuntimeException('Could not initialize a MULTI / EXEC transaction');
            }
        });
    }

    /**
     * Dynamically invokes a Redis command with the specified arguments.
     *
     * @param string $method Command ID.
     * @param array $arguments Arguments for the command.
     *
     * @return MultiExec
     */
    public function __call($method, $arguments)
    {
        $commands = $this->commands;
        $command = $this->client->createCommand($method, $arguments);

        $this->client->executeCommand($command)->then(function ($response) use ($command, $commands) {
            if (!$response instanceof StatusResponse || $response != 'QUEUED') {
                throw new RuntimeException('Unexpected response in MULTI / EXEC [expected +QUEUED]');
            }

            $commands->enqueue($command);
        });

        return $this;
    }

    /**
     * This method is an alias for execute().
     *
     * @return PromiseInterface
     */
    public function exec()
    {
        return $this->execute();
    }

    /**
     * Handles the actual execution of the whole transaction.
     *
     * @return PromiseInterface
     */
    public function execute()
    {
        $commands = $this->commands;
        $command = $this->client->createCommand('EXEC');

        return $this->client->executeCommand($command)->then(function ($responses) use ($commands) {
            $size = count($responses);
            $processed = [];

            for ($i = 0; $i < $size; $i++) {
                $command = $commands->dequeue();
                $response = $responses[$i];

                unset($responses[$i]);

                if (!$response instanceof ResponseInterface) {
                    $response = $command->parseResponse($response);
                }

                $processed[$i] = $response;
            }

            return $processed;
        });
    }
}
