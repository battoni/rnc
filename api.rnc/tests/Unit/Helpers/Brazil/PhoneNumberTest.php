<?php

use App\Helpers\Brazil\PhoneNumber;

it('canonicalises numbers to digits with the country code', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'plain mobile' => ['11999998888', '5511999998888'],
    'masked mobile' => ['(11) 99999-8888', '5511999998888'],
    'spaced with plus' => ['+55 11 99999-8888', '5511999998888'],
    'landline (10 digits)' => ['1133334444', '551133334444'],
    'already country-coded' => ['5511999998888', '5511999998888'],
]);

it('returns null when there are no digits', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'only symbols' => ['(--) -----'],
]);
