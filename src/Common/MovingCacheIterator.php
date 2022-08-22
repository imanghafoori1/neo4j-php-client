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

use AppendIterator;
use Iterator;
use SplQueue;

/**
 * @template TKey
 * @template TValue
 */
class MovingCacheIterator implements Iterator
{
    private int $size;
    /** @var Iterator<TKey, TValue> */
    private Iterator $it;
    private int $position = 0;
    /** @var SplQueue<array{key: TKey, position: int, value: TValue}> */
    private SplQueue $cache;

    public function __construct(Iterator $it, int $size)
    {
        $this->it = $it;
        $this->size = $size;
        $this->cache = new SplQueue();
    }

    public function current()
    {
        return $this->it->current();
    }

    public function next()
    {
        $this->cache->enqueue([
            'key' => $this->it->key(),
            'position' => $this->position,
            'value' => $this->it->current(),
        ]);

        if ($this->cache->count() > $this->size) {
            $this->cache->dequeue();
        }

        $this->it->next();
        ++$this->position;
    }

    public function key()
    {
        $this->it->key();
    }

    public function valid(): bool
    {
        return $this->it->valid();
    }

    public function rewind(): void
    {
        $append = new AppendIterator();

        $append->append($this->cache);
        $append->append($this->it);

        $this->it = $append;
        $this->position = $this->cache->bottom()['position'];
        $this->cache = new SplQueue();
    }
}
