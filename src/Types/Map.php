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
use function array_key_last;
use ArrayAccess;
use function count;
use function func_num_args;
use function is_iterable;
use Iterator;
use Laudis\Neo4j\Databags\Pair;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;
use function sprintf;
use stdClass;

/**
 * An immutable ordered map of items.
 *
 * @template TValue
 *
 * @extends AbstractCypherSequence<TValue>
 *
 * @implements Iterator<string, TValue>
 * @implements ArrayAccess<string, TValue>
 */
class Map extends AbstractCypherSequence implements Iterator, ArrayAccess
{
    /**
     * @psalm-mutation-free
     */
    protected function castToKey($key, int $position): string
    {
        if ($this->isStringable($key)) {
            return (string) $key;
        }

        return (string) $position;
    }

    /**
     * Returns the first pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function first(): Pair
    {
        $this->rewind();
        foreach ($this as $key => $value) {
            return new Pair($key, $value);
        }
        throw new OutOfBoundsException('Cannot grab first element of an empty map');
    }

    /**
     * Returns the last pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function last(): Pair
    {
        $array = $this->toArray();
        if (count($array) === 0) {
            throw new OutOfBoundsException('Cannot grab last element of an empty map');
        }

        $key = array_key_last($array);

        return new Pair($key, $array[$key]);
    }

    /**
     * Returns the pair at the nth position of the map.
     *
     * @return Pair<string, TValue>
     */
    public function skip(int $position): Pair
    {
        $i = 0;
        foreach ($this as $key => $value) {
            if ($i === $position) {
                return new Pair($key, $value);
            }
            ++$i;
        }

        throw new OutOfBoundsException(sprintf('Cannot skip to a pair at position: %s', $position));
    }

    /**
     * Returns the keys in the map in order.
     *
     * @return ArrayList<string>
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function keys(): ArrayList
    {
        return ArrayList::fromIterable((static function () {
            foreach ($this as $key => $value) {
                yield (string) $key;
            }
        })());
    }

    /**
     * Returns the pairs in the map in order.
     *
     * @return ArrayList<Pair<string, TValue>>
     */
    public function pairs(): ArrayList
    {
        return ArrayList::fromIterable((static function () {
            foreach ($this as $key => $value) {
                yield new Pair($key, $value);
            }
        })());
    }

    /**
     * Create a new map sorted by keys. Natural ordering will be used if no comparator is provided.
     *
     * @param (callable(string, string):int)|null $comparator
     *
     * @return static<TValue>
     */
    public function ksorted(callable $comparator = null): Map
    {
        return $this->withOperation(static function (Map $self) use ($comparator) {
            $pairs = $self->pairs()->sorted(static function (Pair $x, Pair $y) use ($comparator) {
                if ($comparator) {
                    return $comparator($x->getKey(), $y->getKey());
                }

                return $x->getKey() <=> $y->getKey();
            });

            foreach ($pairs as $pair) {
                yield $pair->getKey() => $pair->getValue();
            }
        });
    }

    /**
     * Returns the values in the map in order.
     *
     * @return ArrayList<TValue>
     */
    public function values(): ArrayList
    {
        return ArrayList::fromIterable((static function () {
            yield from $this;
        })());
    }

    /**
     * Creates a new map using exclusive or on the keys.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function xor(iterable $map): Map
    {
        return $this->withOperation(static function (Map $self) use ($map) {
            $map = Map::fromIterable($map);
            foreach ($self as $key => $value) {
                if (!$map->hasKey($key)) {
                    yield $key => $value;
                }
            }

            foreach ($map as $key => $value) {
                if (!$self->hasKey($key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return static<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    public function merge(iterable $values): Map
    {
        return $this->withOperation(static function (Map $self) use ($values) {
            $tbr = $self->toArray();
            $values = Map::fromIterable($values);

            foreach ($values as $key => $value) {
                $tbr[$key] = $value;
            }

            yield from $tbr;
        });
    }

    /**
     * Creates a union of this and the provided map. The items in the original map take precedence.
     *
     * @param iterable<mixed, TValue> $map
     *
     * @return static<TValue>
     */
    public function union(iterable $map): Map
    {
        return $this->withOperation(static function (Map $self) use ($map) {
            $map = Map::fromIterable($map)->toArray();
            $x = $self->toArray();

            yield from $x;

            foreach ($map as $key => $value) {
                if (!array_key_exists($key, $x)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that don't exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     *
     * @psalm-immutable
     */
    public function intersect(iterable $map): Map
    {
        return $this->withOperation(static function (Map $self) use ($map) {
            $map = Map::fromIterable($map)->toArray();
            foreach ($self as $key => $value) {
                if (array_key_exists($key, $map)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that also exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function diff(iterable $map): Map
    {
        return $this->withOperation(static function (Map $self) use ($map) {
            $map = Map::fromIterable($map)->toArray();
            foreach ($self as $key => $value) {
                if (!array_key_exists($key, $map)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Gets the value with the provided key. If a default value is provided, it will return the default instead of throwing an error when the key does not exist.
     *
     * @template TDefault
     *
     * @param TDefault $default
     *
     * @throws OutOfBoundsException
     *
     * @return (func_num_args() is 1 ? TValue : TValue|TDefault)
     */
    public function get(string $key, $default = null)
    {
        if (!$this->offsetExists($key)) {
            if (func_num_args() === 1) {
                throw new OutOfBoundsException(sprintf('Cannot get item in sequence with key: %s', $key));
            }

            return $default;
        }

        return $this->offsetGet($key);
    }

    public function jsonSerialize()
    {
        if ($this->isEmpty()) {
            return new stdClass();
        }

        return parent::jsonSerialize();
    }

    /**
     * @param mixed $default
     */
    public function getAsString(string $key, $default = null): string
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toString($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'string');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsInt(string $key, $default = null): int
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toInt($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'int');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsFloat(string $key, $default = null): float
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toFloat($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'float');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsBool(string $key, $default = null): bool
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toBool($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'bool');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return null
     */
    public function getAsNull(string $key, $default = null)
    {
        if (func_num_args() === 1) {
            /** @psalm-suppress UnusedMethodCall */
            $this->get($key);
        }

        return TypeCaster::toNull();
    }

    /**
     * @template U
     *
     * @param class-string<U> $class
     * @param mixed           $default
     *
     * @return U
     */
    public function getAsObject(string $key, string $class, $default = null): object
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toClass($value, $class);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, $class);
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return Map<mixed>
     */
    public function getAsMap(string $key, $default = null): Map
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }

        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, __CLASS__);
        }

        return new Map($value);
    }

    /**
     * @param mixed $default
     *
     * @return ArrayList<mixed>
     */
    public function getAsArrayList(string $key, $default = null): ArrayList
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, ArrayList::class);
        }

        return new ArrayList($value);
    }

    /**
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return Map<Value>
     */
    public static function fromIterable(iterable $iterable): Map
    {
        return new self($iterable);
    }
}
