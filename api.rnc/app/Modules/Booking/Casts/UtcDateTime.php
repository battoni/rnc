<?php

namespace App\Modules\Booking\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A moment stored in UTC whatever `APP_TIMEZONE` says (plan AM9): the app runs in São Paulo time,
 * and Eloquent's own `datetime` writes and reads in that zone. Read back as UTC; the caller shows
 * it in the zone it wants.
 *
 * @implements CastsAttributes<CarbonImmutable|null, DateTimeInterface|string|null>
 */
class UtcDateTime implements CastsAttributes
{
    private const FORMAT = 'Y-m-d H:i:s';

    private const ZONE = 'UTC';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::FORMAT, (string) $value, self::ZONE) ?: null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $moment = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);

        return $moment->utc()->format(self::FORMAT);
    }
}
