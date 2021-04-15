<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Enum\AccessMode;

final class SessionConfiguration
{
    public const DEFAULT_DATABASE = 'neo4j';
    public const DEFAULT_FETCH_SIZE = 1000;
    public const DEFAULT_ACCESS_MODE = 'WRITE';
    public const DEFAULT_BOOKMARKS = '[]';

    /** @var callable():(string|null)|string|null */
    private $database;
    /** @var callable():(int|null)|int|null */
    private $fetchSize;
    /** @var callable():(AccessMode|null)|AccessMode|null */
    private $accessMode;
    /** @var callable():(iterable<string>|null)|iterable<string>|null */
    private $bookmarks;

    /**
     * @param callable():(string|null)|string|null                     $database
     * @param callable():(int|null)|int|null                           $fetchSize
     * @param callable():(AccessMode|null)|AccessMode|null             $defaultAccessMode
     * @param callable():(iterable<string>|null)|iterable<string>|null $bookmarks
     */
    public function __construct(
        $database = null,
        $fetchSize = null,
        $defaultAccessMode = null,
        $bookmarks = null
    ) {
        $this->database = $database;
        $this->fetchSize = $fetchSize;
        $this->accessMode = $defaultAccessMode;
        $this->bookmarks = $bookmarks;
    }

    /**
     * @psalm-immutable
     *
     * @param callable():(string|null)|string|null                $database
     * @param callable():(int|null)|int|null                      $fetchSize
     * @param callable():(AccessMode|null)|AccessMode|null        $defaultAccessMode
     * @param callable():(iterable<string>|null)|iterable<string> $bookmarks
     */
    public static function create($database = null, $fetchSize = null, $defaultAccessMode = null, $bookmarks = null): self
    {
        return new self($database, $fetchSize, $defaultAccessMode, $bookmarks);
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @param string|callable():(string|null)|null $database
     */
    public function withDatabase($database): self
    {
        return new self($database, $this->fetchSize, $this->accessMode, $this->bookmarks);
    }

    /**
     * @param callable():(int|null)|int|null $size
     */
    public function withFetchSize($size): self
    {
        return new self($this->database, $size, $this->accessMode, $this->bookmarks);
    }

    /**
     * @param callable():(AccessMode|null)|AccessMode|null $defaultAccessMode
     */
    public function withAccessMode($defaultAccessMode): self
    {
        return new self($this->database, $this->fetchSize, $defaultAccessMode, $this->bookmarks);
    }

    /**
     * @param callable():(iterable<string>|null)|iterable<string>|null $bookmarks
     */
    public function withBookmarks($bookmarks): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $bookmarks);
    }

    public function getFetchSize(): int
    {
        $fetchSize = is_callable($this->fetchSize) ? call_user_func($this->fetchSize) : $this->fetchSize;

        return $fetchSize ?? self::DEFAULT_FETCH_SIZE;
    }

    public function getAccessMode(): AccessMode
    {
        $accessMode = is_callable($this->accessMode) ? call_user_func($this->accessMode) : $this->accessMode;

        return $accessMode ?? AccessMode::WRITE();
    }

    public function getDatabase(): string
    {
        $database = is_callable($this->database) ? call_user_func($this->database) : $this->database;

        return $database ?? self::DEFAULT_DATABASE;
    }

    public function getBookmarks(): iterable
    {
        $bookmarks = is_callable($this->bookmarks) ? call_user_func($this->bookmarks) : $this->bookmarks;

        return $bookmarks ?? [];
    }
}
