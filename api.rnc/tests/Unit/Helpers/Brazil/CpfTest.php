<?php

use App\Helpers\Brazil\Cpf;

it('normalizes a formatted cpf to digits only', function () {
    expect(Cpf::normalize('111.444.777-35'))->toBe('11144477735')
        ->and(Cpf::normalize('11144477735'))->toBe('11144477735');
});

it('returns null when normalizing an empty or null value', function () {
    expect(Cpf::normalize(null))->toBeNull()
        ->and(Cpf::normalize(''))->toBeNull();
});

it('validates a correct cpf across formats', function () {
    expect(Cpf::isValid('111.444.777-35'))->toBeTrue()
        ->and(Cpf::isValid('11144477735'))->toBeTrue()
        ->and(Cpf::isValid('390.533.447-05'))->toBeTrue();
});

it('rejects an invalid cpf', function () {
    expect(Cpf::isValid('11144477734'))->toBeFalse()
        ->and(Cpf::isValid('123'))->toBeFalse()
        ->and(Cpf::isValid(null))->toBeFalse();
});

it('rejects repeated-digit sequences', function () {
    expect(Cpf::isValid('11111111111'))->toBeFalse()
        ->and(Cpf::isValid('000.000.000-00'))->toBeFalse();
});
