<?php

use App\Modules\Fiscal\Helpers\AccessKey;

/*
 * The NF-e access key (rnc-2 R16, A13; `facts.md` §5, marked to confirm): 44 digits, the last a
 * mod-11 check digit over the first 43 with weights 2–9 from the right (a remainder of 0 or 1 gives
 * 0), the emitter's CNPJ at digits 7–20 and the model at 21–22. The expected keys below were worked
 * out by hand (a separate script), not by the class under test.
 */
describe('the check digit', function () {
    it('accepts a key whose last digit is the mod-11 of the first 43 (A13)', function () {
        expect(AccessKey::isValid('35261011222333000181550010000001231123456783'))->toBeTrue()
            ->and(AccessKey::checkDigit('3526101122233300018155001000000123112345678'))->toBe(3);
    });

    it('gives 0 when the remainder is 0 or 1', function () {
        expect(AccessKey::checkDigit('3526101122233300018155001000000123100000003'))->toBe(0)
            ->and(AccessKey::checkDigit('3526101122233300018155001000000123100000008'))->toBe(0)
            ->and(AccessKey::isValid('35261011222333000181550010000001231000000030'))->toBeTrue();
    });

    it('refuses a key with a wrong check digit (A13 invalid_key)', function () {
        expect(AccessKey::isValid('35261011222333000181550010000001231123456784'))->toBeFalse()
            ->and(AccessKey::isValid('35261011222333000181550010000001231123456780'))->toBeFalse();
    });
});

describe('the shape', function () {
    it('refuses anything that is not exactly 44 digits', function (string $key) {
        expect(AccessKey::isValid($key))->toBeFalse();
    })->with([
        'short' => ['3526101122233300018155001000000123112345678'],
        'long' => ['352610112223330001815500100000012311234567833'],
        'a letter' => ['3526101122233300018155001000000123112345678X'],
        'spaces' => ['3526 1011 2223 3300 0181 5500 1000 0001 2311 2345 6783'],
        'empty' => [''],
    ]);

    it('refuses a key whose emitter CNPJ fails its own check digits', function () {
        $first43 = '3526101122233300018255001000000123112345678';

        expect(AccessKey::isValid($first43.AccessKey::checkDigit($first43)))->toBeFalse();
    });

    it('reads the emitter CNPJ and the model out of the key', function () {
        $key = '35261011222333000181550010000001231123456783';

        expect(AccessKey::emitterCnpj($key))->toBe('11222333000181')
            ->and(AccessKey::model($key))->toBe('55');
    });
});
