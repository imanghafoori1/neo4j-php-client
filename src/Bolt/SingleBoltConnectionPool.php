<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use function explode;
use function extension_loaded;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\SingleThreadedSemaphore;
use Laudis\Neo4j\Common\SysVSemaphore;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use function microtime;
use Psr\Http\Message\UriInterface;
use function random_int;
use RuntimeException;

class SingleBoltConnectionPool implements ConnectionPoolInterface
{
    private SemaphoreInterface $semaphore;
    private UriInterface $uri;

    /** @var list<BoltConnection> */
    private array $activeConnections = [];
    private DriverConfiguration $config;
    private AuthenticateInterface $auth;

    public function __construct(UriInterface $uri, DriverConfiguration $config, AuthenticateInterface $auth)
    {
        $key = $uri->getHost().':'.$uri->getPort().':'.$config->getUserAgent().':'.$auth->toString($uri);
        if (extension_loaded('ext-sysvsem')) {
            $this->semaphore = SysVSemaphore::create($key, $config->getMaxPoolSize());
        } else {
            $this->semaphore = SingleThreadedSemaphore::create($key, $config->getMaxPoolSize());
        }

        $this->uri = $uri;
        $this->config = $config;
        $this->auth = $auth;
    }

    public function acquire(SessionConfiguration $config): BoltConnection
    {
        $generator = $this->semaphore->wait();
        $start = microtime(true);

        while ($generator->valid()) {
            $generator->next();
            $this->guardTiming($start);

            $connection = $this->returnAnyAvailableConnection();
            if ($connection !== null) {
                return $connection;
            }
        }

        return $this->returnAnyAvailableConnection() ?? $this->createNewConnection($config);
    }

    public function release(ConnectionInterface $connection): void
    {
        $this->semaphore->post();
        $connection->close();

        foreach ($this->activeConnections as $i => $activeConnection) {
            if ($connection === $activeConnection) {
                array_splice($this->activeConnections, $i, 1);

                return;
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function guardTiming(float $start): void
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed > $this->config->getAcquireConnectionTimeout()) {
            throw new RuntimeException(sprintf('Connection to %s timed out after %s seconds', $this->uri->__toString(), $elapsed));
        }
    }

    private function returnAnyAvailableConnection(): ?BoltConnection
    {
        $streamingConnections = [];
        foreach ($this->activeConnections as $activeConnection) {
            if ($activeConnection->getServerState() === 'READY') {
                return $activeConnection;
            }

            if ($activeConnection->getServerState() === 'STREAMING' || $activeConnection->getServerState() === 'TX_STREAMING') {
                $streamingConnections[] = $activeConnection;
            }
        }

        if (count($streamingConnections) > 0) {
            $streamingConnection = $streamingConnections[random_int(0, count($streamingConnections) - 1)];

            $streamingConnection->consumeResults();

            return $streamingConnection;
        }

        return null;
    }

    private function createNewConnection(SessionConfiguration $config): BoltConnection
    {
        $factory = BoltFactory::fromVariables($this->uri, $this->auth, $this->config);
        [$bolt, $response] = $factory->build();

        $config = new ConnectionConfiguration(
            $response['server'],
            $this->uri,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            $this->config,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        $tbr = new BoltConnection($factory, $bolt, $config);

        $this->activeConnections[] = $tbr;

        return $tbr;
    }
}