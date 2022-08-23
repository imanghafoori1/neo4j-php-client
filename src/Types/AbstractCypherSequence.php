<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Types;

use function array_key_exists;
use function array_reverse;
use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Closure;
use Countable;
use Generator;
use function get_class;
use function get_class_vars;
use function get_object_vars;
use function implode;
use function var_dump;
use const INF;
use function is_array;
use function is_callable;
use function is_countable;
use function is_numeric;
use function is_object;
use function is_string;
use Iterator;
use function iterator_to_array;
use JsonSerializable;
use Laudis\Neo4j\Common\RewindableGenerator;
use function method_exists;
use OutOfBoundsException;
use function property_exists;
use ReturnTypeWillChange;
use function sprintf;
use Throwable;
use UnexpectedValueException;

/**
 * Abstract immutable sequence with basic functional methods.
 *
 * @template TValue
 *
 * @implements ArrayAccess<array-key, TValue>
 * @implements Iterator<array-key, TValue>
 *
 * @psalm-consistent-templates
 */
abstract class AbstractCypherSequence implements Countable, JsonSerializable, ArrayAccess, Iterator
{
    /**
     * @var Iterator<mixed, TValue>
     */
    protected Iterator $iterator;
    /**
     * @var iterable<mixed, TValue>
     */
    protected $original;

    private int $position = 0;

    /**
     * @param iterable<mixed, TValue>|callable():iterable<mixed, TValue> $iterable
     *
     * @psalm-mutation-free
     *
     * @psalm-suppress ImpureFunctionCall
     * @psalm-suppress ImpureMethodCall
     */
    public function __construct($iterable = [])
    {
        $iterable = is_callable($iterable) ? $iterable() : $iterable;
        $this->original = $iterable;

        if ($iterable instanceof Iterator) {
            $this->iterator = $iterable;
        } elseif (is_array($iterable)) {
            $this->iterator = new ArrayIterator($iterable);
        } else {
            $this->iterator = (function () use ($iterable): Generator {
                yield from $iterable;
            })();
        }
    }

    /**
     * @param mixed $key
     *
     * @return array-key
     *
     * @psalm-mutation-free
     */
    abstract protected function castToKey($key, int $position);

    /**
     * Copies the sequence.
     *
     * @return static<TValue>
     *
     * @psalm-mutation-free
     */
    final public function copy(): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $x) {
            yield from $x;
        });
    }

    /**
     * @template TNewValue
     *
     * @param callable(static<TValue>):iterable<array-key, TNewValue> $operation
     *
     * @return static<TNewValue>
     *
     * @psalm-external-mutation-free
     */
    protected function withOperation($operation): self
    {
        $tbr = clone $this;

        /** @psalm-suppress ImpureMethodCall */
        $operation = Closure::fromCallable($operation)->bindTo(null);

        /**
         * @var static<TNewValue> $tbr
         * @psalm-suppress ImpurePropertyAssignment
         */
        $tbr->iterator = new RewindableGenerator(clone $this, $operation);

        return $tbr;
    }

    /**mixed
     * Returns whether the sequence is empty.
     *
     * @psalm-suppress UnusedForeachValue
     */
    final public function isEmpty(): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($this as $ignored) {
            return false;
        }

        return true;
    }

    /**
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
     *
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return static<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    abstract public function merge(iterable $values): self;

    /**
     * Checks if the sequence contains the given key.
     *
     * @param array-key $key
     */
    final public function hasKey($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Checks if the sequence contains the given value. The equality check is strict.
     *
     * @param TValue $value
     */
    final public function hasValue($value): bool
    {
        return $this->find($value) !== false;
    }

    /**
     * Creates a filtered the sequence with the provided callback.
     *
     * @param callable(TValue, array-key):bool $callback
     *
     * @return static<TValue>
     *
     * @psalm-mutation-free
     */
    final public function filter(callable $callback): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $self) use ($callback) {
            foreach ($self as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Maps the values of this sequence to a new one with the provided callback.
     *
     * @template ReturnType
     *
     * @param callable(TValue, array-key):ReturnType $callback
     *
     * @return static<ReturnType>
     *
     * @psalm-mutation-free
     */
    final public function map(callable $callback): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $self) use ($callback) {
            foreach ($self as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Reduces this sequence with the given callback.
     *
     * @template TInitial
     *
     * @param callable(TInitial|null, TValue, array-key):TInitial $callback
     * @param TInitial|null                                       $initial
     *
     * @return TInitial
     */
    final public function reduce(callable $callback, $initial = null)
    {
        foreach ($this as $key => $value) {
            $initial = $callback($initial, $value, $key);
        }

        return $initial;
    }

    /**
     * Finds the position of the value within the sequence.
     *
     * @param TValue $value
     *
     * @return false|array-key returns the key of the value if it is found, false otherwise
     */
    final public function find($value)
    {
        foreach ($this as $i => $x) {
            if ($value === $x) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Creates a reversed sequence.
     *
     * @return static<TValue>
     *
     * @psalm-mutation-free
     */
    public function reversed(): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $self) {
            yield from array_reverse(iterator_to_array($self));
        });
    }

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return static<TValue>
     *
     * @psalm-mutation-free
     */
    public function slice(int $offset, int $length = null): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $self) use ($offset, $length) {
            if ($length !== 0) {
                $count = -1;
                $length ??= INF;
                foreach ($self as $key => $value) {
                    ++$count;
                    if ($count < $offset) {
                        continue;
                    }

                    yield $key => $value;
                    if ($count === ($offset + $length - 1)) {
                        break;
                    }
                }
            }
        });
    }

    /**
     * Creates a sorted sequence. If the comparator is null it will use natural ordering.
     *
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static<TValue>
     *
     * @psalm-mutation-free
     */
    public function sorted(?callable $comparator = null): self
    {
        return $this->withOperation(static function (AbstractCypherSequence $self) use ($comparator) {
            $iterable = iterator_to_array($self);

            if ($comparator) {
                uasort($iterable, $comparator);
            } else {
                asort($iterable);
            }

            yield from $iterable;
        });
    }

    /**
     * Creates a list from the arrays and objects in the sequence whose values corresponding with the provided key.
     *
     * @return ArrayList<mixed>
     *
     * @psalm-mutation-free
     */
    public function pluck(string $key): ArrayList
    {
        return new ArrayList(function () use ($key) {
            foreach ($this as $value) {
                if ((is_array($value) && array_key_exists(
                            $key,
                            $value
                        )) || ($value instanceof ArrayAccess && $value->offsetExists($key))) {
                    yield $value[$key];
                } elseif (is_object($value) && property_exists($value, $key)) {
                    yield $value->$key;
                }
            }
        });
    }

    /**
     * Uses the values found at the provided key as the key for the new Map.
     *
     * @return Map<mixed>
     *
     * @psalm-mutation-free
     */
    public function keyBy(string $key): Map
    {
        return new Map(function () use ($key) {
            foreach ($this as $value) {
                if (((is_array($value) && array_key_exists(
                                $key,
                                $value
                            )) || ($value instanceof ArrayAccess && $value->offsetExists($key))) && $this->isStringable(
                        $value[$key]
                    )) {
                    yield $value[$key] => $value;
                } elseif (is_object($value) && property_exists($value, $key) && $this->isStringable($value->$key)) {
                    yield $value->$key => $value;
                } else {
                    throw new UnexpectedValueException('Cannot convert the value to a string');
                }
            }
        });
    }

    /**
     * Joins the values within the sequence together with the provided glue. If the glue is null, it will be an empty string.
     */
    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->toArray());
    }

    /**
     * Iterates over the sequence and applies the callable.
     *
     * @param callable(TValue, array-key):void $callable
     *
     * @return static<TValue>
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (is_array($this->original) || $this->original instanceof ArrayAccess) {
            try {
                /** @var TValue */
                return $this->original[$offset];
            } catch (Throwable $e) {
                throw new OutOfBoundsException(sprintf('Offset: "%s" does not exists in object of instance: %s', $offset, static::class), 0, $e);
            }
        }

        $this->rewind();
        while ($this->valid()) {
            if ($this->key() === $offset) {
                return $this->current();
            }
            $this->next();
        }

        throw new OutOfBoundsException(sprintf('Offset: "%s" does not exists in object of instance: %s', $offset, static::class));
    }

    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    /**
     * @psalm-suppress UnusedForeachValue
     */
    public function offsetExists($offset): bool
    {
        if (is_array($this->original)) {
            return array_key_exists($offset, $this->original);
        }

        if ($this->original instanceof ArrayAccess) {
            return $this->original->offsetExists($offset);
        }

        while ($this->valid()) {
            if ($this->key() === $offset) {
                return true;
            }
            $this->next();
        }

        return false;
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<array-key, TValue>
     */
    final public function toArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<array-key, TValue|array>
     */
    final public function toRecursiveArray(): array
    {
        return $this->map(static function ($x) {
            if ($x instanceof self) {
                return $x->toRecursiveArray();
            }

            return $x;
        })->toArray();
    }

    final public function count(): int
    {
        if (is_countable($this->original)) {
            return count($this->original);
        }

        $this->preload();

        return $this->position;
    }

    /**
     * @return TValue
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->iterator->current();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
        $this->position = 0;
    }

    public function next(): void
    {
        ++$this->position;
        $this->iterator->next();
    }

    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->castToKey($this->iterator->key(), $this->position);
    }

    /**
     * @param mixed $key
     *
     * @psalm-mutation-free
     */
    protected function isStringable($key): bool
    {
        return is_string($key) || is_numeric($key) || (is_object($key) && method_exists($key, '__toString'));
    }

    public function __serialize(): array
    {
        $tbr = get_object_vars($this);

        $tbr['original'] = iterator_to_array($this->iterator);
        $tbr['iterator'] = new ArrayIterator($tbr['original']);

        return $tbr;
    }

    public function preload(): void
    {
        while ($this->valid()) {
            $this->next();
        }
    }
}
