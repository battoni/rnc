<?php

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The trait is read-only sugar over the stored map. What it must never do is hand a screen an empty
 * string because the language it asked for has not been written yet.
 */

function translatableRow(mixed $name): Model
{
    $model = new class extends Model
    {
        use HasTranslations;

        protected $guarded = [];

        protected $casts = ['name' => 'array'];
    };

    $model->setAttribute('name', $name);

    return $model;
}

it('reads the asked-for language out of the map', function () {
    expect(translatableRow(['pt-BR' => 'Praia', 'en' => 'Beach'])->translate('name', 'en'))->toBe('Beach');
});

it('falls back instead of rendering nothing', function () {
    config()->set('app.locale', 'pt-BR');

    expect(translatableRow(['pt-BR' => 'Praia'])->translate('name', 'en'))->toBe('Praia');
});

it('passes a plain string through, for a column that predates the map', function () {
    expect(translatableRow('Praia')->translate('name', 'en'))->toBe('Praia');
});

it('answers null for a field that holds neither a map nor a string', function () {
    expect(translatableRow(null)->translate('name', 'en'))->toBeNull();
});
