<?php

use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Helpers\BusinessHours;
use App\Modules\Partner\Services\ReferralSla;
use Carbon\CarbonImmutable;

/*
 * The SLA (rnc-2 R8, A7): 24 business hours from the referral to the first contact. While open:
 * ok (< 12 h) · atencao (12–24) · estourado (≥ 24); once contacted: cumprido (≤ 24) / fora; a
 * declined referral is encerrado. Sent Friday 13/11/2026 16:00 (Friday closes at 17:00).
 */
function slaAt(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'America/Sao_Paulo');
}

/** @return array{state: string, hours: float} */
function slaOf(string $status, string $now, ?string $contactedAt = null, string $sentAt = '2026-11-13 16:00'): array
{
    $hours = new BusinessHours([
        ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
        ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
    ]);

    return ReferralSla::measure(ReferralStatus::from($status), slaAt($sentAt), $contactedAt === null ? null : slaAt($contactedAt), slaAt($now), $hours);
}

it('holds 24 business hours, warning from 12', function () {
    expect(ReferralSla::HOURS)->toBe(24)->and(ReferralSla::WARN_HOURS)->toBe(12);
});

it('is at one hour on Monday 8:00 for a referral sent Friday 16:00 (A7)', function () {
    expect(slaOf('enviado', '2026-11-16 08:00'))->toBe(['state' => 'ok', 'hours' => 1.0]);
});

it('turns atencao at 12 hours and estourado at 24 (A7)', function (string $now, string $state, float $hours) {
    expect(slaOf('enviado', $now))->toBe(['state' => $state, 'hours' => $hours]);
})->with([
    'just under 12' => ['2026-11-17 08:30', 'ok', 11.5],
    'at 12' => ['2026-11-17 09:00', 'atencao', 12.0],
    'just under 24' => ['2026-11-18 10:30', 'atencao', 23.5],
    'at 24' => ['2026-11-18 11:00', 'estourado', 24.0],
    'well past' => ['2026-11-19 18:00', 'estourado', 41.0],
]);

it('keeps counting while accepted and not yet contacted', function () {
    expect(slaOf('aceito', '2026-11-18 11:00'))->toBe(['state' => 'estourado', 'hours' => 24.0]);
});

it('counts a holiday as zero (A7)', function () {
    expect(slaOf('enviado', '2026-11-23 08:00', sentAt: '2026-11-19 16:00'))->toBe(['state' => 'ok', 'hours' => 2.0]);
});

it('is cumprido for a first contact at 20 hours and fora at 30 (A7)', function () {
    expect(slaOf('contato', '2026-11-25 10:00', contactedAt: '2026-11-17 17:00'))->toBe(['state' => 'cumprido', 'hours' => 20.0])
        ->and(slaOf('reuniao', '2026-11-25 10:00', contactedAt: '2026-11-18 17:00'))->toBe(['state' => 'fora', 'hours' => 30.0])
        ->and(slaOf('fechado', '2026-11-25 10:00', contactedAt: '2026-11-18 11:00'))->toBe(['state' => 'cumprido', 'hours' => 24.0]);
});

it('stops the clock at the contact, whatever the time now', function () {
    expect(slaOf('proposta', '2027-01-29 10:00', contactedAt: '2026-11-16 09:00'))->toBe(['state' => 'cumprido', 'hours' => 2.0]);
});

it('is encerrado, at zero, once declined (A7)', function () {
    expect(slaOf('recusado', '2026-11-25 10:00'))->toBe(['state' => 'encerrado', 'hours' => 0.0]);
});
