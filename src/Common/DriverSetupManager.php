<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Common;

use function array_key_exists;
use function array_key_first;
use function array_reduce;
use Countable;
use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\DriverFactory;
use const PHP_INT_MIN;
use RuntimeException;
use SplPriorityQueue;
use function sprintf;

/**
 * @template ResultFormat
 */
class DriverSetupManager implements Countable
{
    private const DEFAULT_DRIVER_CONFIG = 'bolt://localhost:7687';

    /** @var array<string, SplPriorityQueue<int, DriverSetup>> */
    private array $driverSetups = [];
    private array $drivers = [];
    private ?string $default = null;
    private FormatterInterface $formatter;
    private DriverConfiguration $configuration;

    /**
     * @param FormatterInterface<ResultFormat> $formatter
     * @param DriverConfiguration $configuration
     */
    public function __construct(FormatterInterface $formatter, DriverConfiguration $configuration)
    {
        $this->formatter = $formatter;
        $this->configuration = $configuration;
    }

    public function addSetup(DriverSetup $setup, ?string $alias = null, ?int $priority = 0): void
    {
        $alias ??= $this->decideAlias($alias);

        $this->driverSetups[$alias] ??= new SplPriorityQueue();
        $this->driverSetups[$alias]->insert($setup, $priority ?? 0);
    }

    /**
     * @return DriverInterface<ResultFormat>
     */
    public function getDriver(?string $alias = null): DriverInterface
    {
        $alias ??= $this->decideAlias($alias);

        if (!array_key_exists($alias, $this->driverSetups)) {
            if ($alias !== 'default') {
                throw new InvalidArgumentException(sprintf('Cannot find a driver setup with alias: "%s"', $alias));
            }

            $this->driverSetups['default'] = new SplPriorityQueue();
            $setup = new DriverSetup(Uri::create(self::DEFAULT_DRIVER_CONFIG), Authenticate::disabled());
            $this->driverSetups['default']->insert($setup, PHP_INT_MIN);

            return $this->getDriver();
        }

        if (array_key_exists($alias, $this->drivers)) {
            return $this->drivers[$alias];
        }

        $urisTried = [];
        foreach ($this->driverSetups[$alias] as $setup) {
            $uri = $setup->getUri();
            $auth = $setup->getAuth();

            $driver = DriverFactory::create($uri, $this->configuration, $auth, $this->formatter);
            $urisTried[] = $uri->__toString();
            if ($driver->verifyConnectivity()) {
                $this->drivers[$alias] = $driver;

                return $driver;
            }
        }

        throw new RuntimeException(sprintf('Cannot connect to any server on alias: %s with Uris: (\'%s\')', $alias, implode('\', ', array_unique($urisTried))));
    }

    public function verifyConnectivity(?string $alias = null): bool
    {
        try {
            $this->getDriver($alias);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @psalm-mutation-free
     */
    private function decideAlias(?string $alias): string
    {
        return $alias ?? $this->default ?? array_key_first($this->driverSetups) ?? 'default';
    }

    public function setDefault(string $default): void
    {
        $this->default = $default;
    }

    public function count(): int
    {
        return array_reduce($this->driverSetups, static fn (int $acc, SplPriorityQueue $x) => $acc + $x->count(), 0);
    }
}