<?php

use App\Helpers\Brazil\Cnpj;

it('normalizes a formatted cnpj to digits only', function () {
    expect(Cnpj::normalize('11.222.333/0001-81'))->toBe('11222333000181')
        ->and(Cnpj::normalize('11222333000181'))->toBe('11222333000181');
});

it('returns null when normalizing an empty or null value', function () {
    expect(Cnpj::normalize(null))->toBeNull()
        ->and(Cnpj::normalize(''))->toBeNull();
});

it('validates a correct cnpj across formats', function () {
    expect(Cnpj::isValid('11.222.333/0001-81'))->toBeTrue()
        ->and(Cnpj::isValid('11222333000181'))->toBeTrue();
});

it('rejects an invalid cnpj', function () {
    expect(Cnpj::isValid('11222333000182'))->toBeFalse()
        ->and(Cnpj::isValid('123'))->toBeFalse()
        ->and(Cnpj::isValid('11111111111111'))->toBeFalse()
        ->and(Cnpj::isValid(null))->toBeFalse();
});
