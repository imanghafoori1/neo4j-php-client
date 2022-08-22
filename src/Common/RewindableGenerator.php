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

use Generator;
use Iterator;
use ReturnTypeWillChange;

/**
 * @template TKey
 * @template TOriginalValue
 * @template TNewValue
 * @template TNewKey
 *
 * @implements Iterator<TNewKey, TNewValue>
 */
class RewindableGenerator implements Iterator
{
    private Iterator $originalIterator;
    private Iterator $newIterator;
    /**
     * @var callable(Iterator<TKey, TOriginalValue>):Iterator<TNewKey, TNewValue>
     */
    private $operation;

    /**
     * @param Iterator<TKey, TOriginalValue> $it
     * @param pure-callable(Iterator<TKey, TOriginalValue>):Generator<TNewKey, TNewValue> $operation
     *
     * @psalm-external-mutation-free
     */
    public function __construct(Iterator $it, callable $operation)
    {
        $this->originalIterator = $it;
        $this->newIterator = $operation($it);
        $this->operation = $operation;
    }

    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->newIterator->current();
    }

    public function next(): void
    {
        $this->newIterator->next();
    }

    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->newIterator->key();
    }

    public function valid(): bool
    {
        return $this->newIterator->valid();
    }

    public function rewind(): void
    {
        $this->originalIterator->rewind();
        $this->newIterator = call_user_func($this->operation, $this->originalIterator);
    }
}
