<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use function array_key_exists;
use function array_splice;
use Bolt\protocol\V4;
use function call_user_func;
use function count;
use Laudis\Neo4j\Common\BoltConnection;
use const PHP_INT_MAX;
use SeekableIterator;

/**
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @implements SeekableIterator<int, list>
 */
final class BoltResult implements SeekableIterator
{
    private BoltConnection $connection;
    private int $fetchSize;
    /** @var list<list>|null */
    private ?array $rows = null;
    private ?array $meta = null;
    /** @var callable(array):void|null */
    private $finishedCallback;
    private int $qid;
    private int $position = 0;
    private int $pullCount = 0;

    public function __construct(BoltConnection $connection, int $fetchSize, int $qid)
    {
        $this->connection = $connection;
        $this->fetchSize = $fetchSize;
        $this->qid = $qid;
        $this->connection->incrementOwner();
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
    }

    /**
     * @param callable(array):void $finishedCallback
     */
    public function setFinishedCallback(callable $finishedCallback): void
    {
        $this->finishedCallback = $finishedCallback;
    }

    private function rowKey(): int
    {
        if ($this->pullCount === -1) {
            return 0;
        }

        if ($this->pullCount === PHP_INT_MAX) {
            return $this->position;
        }

        return $this->position - ($this->pullCount * $this->fetchSize);
    }

    /**
     * Fetches the result and closes the connection when done.
     */
    private function fetchResults(): void
    {
        if ($this->connection->isOpen()) {
            $protocol = $this->connection->getImplementation();
            if (!$protocol instanceof V4) {
                $this->pullCount = PHP_INT_MAX;
                /** @var non-empty-list<list> */
                $meta = $protocol->pullAll(['qid' => $this->qid]);
            } else {
                ++$this->pullCount;
                /** @var non-empty-list<list> */
                $meta = $protocol->pull(['n' => $this->fetchSize, 'qid' => $this->qid]);
            }

            /** @var list<list> $rows */
            $rows = array_splice($meta, 0, count($meta) - 1);
            $this->rows = $rows;

            /** @var array{0: array} $meta */
            $this->handleMetaResults($meta[0]);
        }
    }

    /**
     * @return list
     */
    public function current(): array
    {
        $this->fetchIfNeeded();

        /**
         * Constraints are checked during fetchIfNeeded.
         *
         * @psalm-suppress PossiblyNullArrayAccess
         *
         * @var list
         */
        return $this->rows[$this->rowKey()];
    }

    public function next(): void
    {
        ++$this->position;
        $this->fetchIfNeeded();
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        $this->fetchIfNeeded();

        return array_key_exists($this->rowKey(), $this->rows ?? []);
    }

    public function rewind(): void
    {
    }

    public function __destruct()
    {
        if ($this->connection->isOpen()) {
            $this->discard();
        }
        if ($this->meta === null) {
            $this->connection->decrementOwner();
            $this->connection->close();
        }
    }

    /**
     * Discards the rows and closes the connection when done. All rows are discarded if the protocol is lower than V4 or when $n === -1.
     */
    public function discard(int $n = -1): void
    {
        if ($this->meta === null) {
            $this->rows = [];
            $params = ['n' => $n];
            if ($this->qid !== -1) {
                $params['qid'] = $this->qid;
            }
            if ($this->connection->isOpen()) {
                $v3 = $this->connection->getImplementation();
                if ($v3 instanceof V4) {
                    $meta = $v3->discard($params);
                    $this->handleMetaResults($meta);
                }
            }
        } elseif ($n === -1) {
            $this->pullCount = PHP_INT_MAX;
            $this->meta = [];
        }
    }

    public function seek($offset): void
    {
        $diff = $offset - $this->position;
        if ($diff > 0) {
            $futureFetchCount = (int) ($offset / $this->fetchSize);
            if ($futureFetchCount > $this->pullCount) {
                $this->discard(($futureFetchCount - $this->pullCount) * $this->fetchSize);
                $this->pullCount = $futureFetchCount;
            }

            $this->position = $offset;
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function fetchIfNeeded(): void
    {
        if ($this->rows === null || ($this->rowKey() === count($this->rows) && $this->meta === null)) {
            $this->fetchResults();
            if ($this->meta !== null && $this->finishedCallback) {
                call_user_func($this->finishedCallback, $this->meta);
            }
        }
    }

    private function handleMetaResults(array $array): void
    {
        if (!array_key_exists('has_more', $array) || $array['has_more'] === false) {
            $this->meta = $array;
            $this->connection->decrementOwner();
            $this->connection->close();
        }
    }
}
