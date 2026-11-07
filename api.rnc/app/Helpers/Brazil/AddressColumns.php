<?php

namespace App\Helpers\Brazil;

use Illuminate\Database\Schema\Blueprint;

/**
 * The two column sets a Brazilian address is stored in, as Blueprint macros, so every table that
 * holds one spells it the same way and `AddressData` / `LocalityData` can read any of them.
 *
 * - `$table->addressColumns()` — a full street address: CEP, street, number, complement, district,
 *   city, UF, and an optional coordinate.
 * - `$table->localityColumns()` — a place at city level: UF, city, optional CEP, an optional
 *   free-text region inside the city, and an optional coordinate.
 *
 * The two share `cep`, `city` and `state`, so one table uses one of them, never both.
 * Registered from `AppServiceProvider::boot()`; deleting `Helpers/Brazil/` breaks that line and
 * every migration that calls a macro, which is how a product outside Brazil finds them.
 */
class AddressColumns
{
    /** A UF is always two letters. */
    public const STATE_LENGTH = 2;

    /** Enough for any latitude or longitude, to the centimetre. */
    public const COORDINATE_PRECISION = 10;

    public const COORDINATE_SCALE = 7;

    public static function register(): void
    {
        Blueprint::macro('addressColumns', function (): void {
            /** @var Blueprint $this */
            $this->string('cep')->nullable();
            $this->string('street');
            $this->string('number');
            $this->string('complement')->nullable();
            $this->string('district');
            $this->string('city');
            $this->string('state', AddressColumns::STATE_LENGTH);
            $this->decimal('lat', AddressColumns::COORDINATE_PRECISION, AddressColumns::COORDINATE_SCALE)->nullable();
            $this->decimal('lng', AddressColumns::COORDINATE_PRECISION, AddressColumns::COORDINATE_SCALE)->nullable();
        });

        Blueprint::macro('localityColumns', function (): void {
            /** @var Blueprint $this */
            $this->string('state', AddressColumns::STATE_LENGTH);
            $this->string('city');
            $this->string('cep')->nullable();
            $this->string('region')->nullable();
            $this->decimal('lat', AddressColumns::COORDINATE_PRECISION, AddressColumns::COORDINATE_SCALE)->nullable();
            $this->decimal('lng', AddressColumns::COORDINATE_PRECISION, AddressColumns::COORDINATE_SCALE)->nullable();
        });
    }
}
