<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Laravel's default date format is whole seconds, which would turn a 1 s fast-mode
 * deadline into "some time this second". Every game table uses timestampTz(3), so
 * store microseconds with an explicit UTC offset: exact on Postgres, and a plain
 * string that still compares correctly on SQLite. Pass query bindings through
 * `dbTime()` for the same reason: the query grammar would truncate them too.
 */
trait HasPreciseTimestamps
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }

    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }

    /** A timestamp as the database stores it, for use as a where() binding. */
    public static function dbTime(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d H:i:s.uP');
    }
}
