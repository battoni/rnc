<?php

use App\Helpers\Brazil\AddressData;
use App\Helpers\Brazil\HasAddress;
use App\Helpers\Brazil\LocalityData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

/*
 * No table in the bootstrap stores an address yet, so the block is proved on throwaway tables built
 * here: the macros are what a migration would call, and the model is what a module would declare.
 */

const ADDRESS_TABLE = 'brazil_address_rows';
const LOCALITY_TABLE = 'brazil_locality_rows';

beforeEach(function () {
    Schema::create(ADDRESS_TABLE, function (Blueprint $table) {
        $table->id();
        $table->addressColumns();
    });

    Schema::create(LOCALITY_TABLE, function (Blueprint $table) {
        $table->id();
        $table->localityColumns();
    });
});

function addressBlockRow(): Model
{
    return new class extends Model
    {
        use HasAddress;

        public $timestamps = false;

        protected $guarded = [];

        protected $table = ADDRESS_TABLE;
    };
}

function localityBlockRow(): Model
{
    return new class extends Model
    {
        use HasAddress;

        public $timestamps = false;

        protected $guarded = [];

        protected $table = LOCALITY_TABLE;
    };
}

it('stamps the full street-address column set', function () {
    expect(Schema::getColumnListing(ADDRESS_TABLE))->toEqualCanonicalizing([
        'id', 'cep', 'street', 'number', 'complement', 'district', 'city', 'state', 'lat', 'lng',
    ]);
});

it('stamps the city-level locality column set', function () {
    expect(Schema::getColumnListing(LOCALITY_TABLE))->toEqualCanonicalizing([
        'id', 'state', 'city', 'cep', 'region', 'lat', 'lng',
    ]);
});

it('marks only the optional address columns as nullable', function (string $table, array $nullable) {
    $nullableColumns = collect(Schema::getColumns($table))
        ->filter(fn (array $column) => $column['nullable'])
        ->pluck('name')
        ->all();

    expect($nullableColumns)->toEqualCanonicalizing($nullable);
})->with([
    'address' => [ADDRESS_TABLE, ['cep', 'complement', 'lat', 'lng']],
    'locality' => [LOCALITY_TABLE, ['cep', 'region', 'lat', 'lng']],
]);

it('refuses an address without its required parts', function () {
    addressBlockRow()->newQuery()->insert(['cep' => '01001-000', 'city' => 'São Paulo', 'state' => 'SP']);
})->throws(QueryException::class);

it('reads an address row as AddressData, coordinates as floats', function () {
    $row = addressBlockRow()->newQuery()->create([
        'cep' => '01001-000',
        'street' => 'Praça da Sé',
        'number' => 'S/N',
        'complement' => 'Lado ímpar',
        'district' => 'Sé',
        'city' => 'São Paulo',
        'state' => 'SP',
        'lat' => '-23.5503099',
        'lng' => '-46.6342009',
    ])->fresh();

    $address = $row->addressData();

    expect($address)->toBeInstanceOf(AddressData::class)
        ->and($address->toArray())->toBe([
            'street' => 'Praça da Sé',
            'number' => 'S/N',
            'district' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'cep' => '01001-000',
            'complement' => 'Lado ímpar',
            'lat' => -23.5503099,
            'lng' => -46.6342009,
        ]);
});

it('keeps an absent coordinate null instead of zero', function () {
    $row = addressBlockRow()->newQuery()->create([
        'street' => 'Rua Um',
        'number' => '10',
        'district' => 'Centro',
        'city' => 'Campinas',
        'state' => 'SP',
    ])->fresh();

    expect($row->addressData())
        ->cep->toBeNull()
        ->complement->toBeNull()
        ->lat->toBeNull()
        ->lng->toBeNull();
});

it('reads a locality row as LocalityData', function () {
    $row = localityBlockRow()->newQuery()->create([
        'state' => 'RJ',
        'city' => 'Rio de Janeiro',
        'region' => 'Zona Sul',
        'lat' => '-22.9068467',
    ])->fresh();

    expect($row->localityData())->toBeInstanceOf(LocalityData::class)
        ->and($row->localityData()->toArray())->toBe([
            'state' => 'RJ',
            'city' => 'Rio de Janeiro',
            'cep' => null,
            'region' => 'Zona Sul',
            'lat' => -22.9068467,
            'lng' => null,
        ]);
});

it('builds the DTOs from a request-shaped array', function () {
    $address = AddressData::from([
        'street' => 'Av. Paulista',
        'number' => '1000',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ]);

    $locality = LocalityData::from(['state' => 'MG', 'city' => 'Belo Horizonte']);

    expect($address->cep)->toBeNull()
        ->and($address->city)->toBe('São Paulo')
        ->and($locality->region)->toBeNull()
        ->and($locality->state)->toBe('MG');
});
