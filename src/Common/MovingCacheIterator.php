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
use ArrayIterator;
use Iterator;
use NoRewindIterator;
use ReturnTypeWillChange;
use SplFixedArray;

/**
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 */
class MovingCacheIterator implements Iterator
{
    /** @var Iterator<TKey, TValue> */
    private Iterator $it;
    private int $position = 0;
    /** @var SplFixedArray<array{key: TKey, position: int, value: TValue}> */
    private SplFixedArray $cache;

    /**
     * @param Iterator<TKey, TValue> $it
     *
     * @psalm-suppress MixedPropertyTypeCoercion false positive
     */
    public function __construct(Iterator $it, int $size)
    {
        $this->it = $it;
        $this->cache = new SplFixedArray($size);
    }

    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->it->current();
    }

    public function next(): void
    {
        $this->cache[$this->position % $this->cache->getSize()] = [
            'key' => $this->it->key(),
            'position' => $this->position,
            'value' => $this->it->current(),
        ];

        $this->it->next();
        ++$this->position;
    }

    #[ReturnTypeWillChange]
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

        $array = array_slice($this->cache->toArray(), 0, min($this->cache->getSize(), $this->position));
        $append->append(new ArrayIterator(array_map(static function (array $x) {
            return $x['value'];
        }, $array)));

        if ($this->it->valid()) {
            $append->append(new NoRewindIterator($this->it));
        }

        $this->it = $append;
        if ($this->position < $this->cache->count()) {
            $this->position = 0;
        } else {
            $this->position = $this->position - $this->cache->count();
        }

        $this->cache = new SplFixedArray($this->cache->getSize());
    }
}
