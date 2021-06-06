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

namespace Laudis\Neo4j\Types;

use Bolt\structures\DateTime as BoltDateTimeAlias;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Exception\TimezoneOffsetException;
use function sprintf;

final class DateTime
{
    private int $seconds;
    private int $nanoseconds;
    private int $tzOffsetSeconds;

    public function __construct(int $seconds, int $nanoseconds, int $tzOffsetSeconds)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
        $this->tzOffsetSeconds = $tzOffsetSeconds;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function getTimeZoneOffsetSeconds(): int
    {
        return $this->tzOffsetSeconds;
    }

    public static function makeFromBoltDateTime(BoltDateTimeAlias $datetime): self
    {
        return new self($datetime->seconds(), $datetime->nanoseconds(), $datetime->tz_offset_seconds());
    }

    /**
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        /** @psalm-suppress PossiblyFalseIterator */
        foreach (DateTimeZone::listAbbreviations() as $tz) {
            /** @psalm-suppress all */
            if ($tz['offset'] === $this->getTimeZoneOffsetSeconds()) {
                return (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
                    ->modify(sprintf('+%s nanoseconds', $this->nanoseconds))
                    ->setTimezone(new DateTimeZone($tz['timezone_id']));
            }
        }

        $message = sprintf('Cannot find an timezone with %s seconds as offset.', $this->tzOffsetSeconds);
        throw new TimezoneOffsetException($message);
    }
}
