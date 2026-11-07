<?php

use Spatie\LaravelData\Normalizers\ArrayableNormalizer;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\FormRequestNormalizer;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;

/*
|--------------------------------------------------------------------------
| spatie/laravel-data — only what this API changes
|--------------------------------------------------------------------------
|
| Every other key keeps the package default: the service provider merges this file over
| `vendor/spatie/laravel-data/config/data.php`, one top-level key at a time.
|
*/

return [

    /*
     * A DTO maps the VALIDATED input, and this list is what makes `XxxDTO::from($request)` do it.
     *
     * The package ships `FormRequestNormalizer` commented out. Without it a FormRequest is just
     * `Arrayable`, `ArrayableNormalizer` answers `$request->all()`, and a field the request never
     * validated — or one an `exclude_*` rule removed — reaches the DTO anyway. The order matters
     * for the same reason: a FormRequest is Arrayable too, so its normalizer has to be asked first.
     */
    'normalizers' => [
        ModelNormalizer::class,
        FormRequestNormalizer::class,
        ArrayableNormalizer::class,
        ObjectNormalizer::class,
        ArrayNormalizer::class,
        JsonNormalizer::class,
    ],

];
