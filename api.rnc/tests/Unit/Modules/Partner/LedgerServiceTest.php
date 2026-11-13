<?php

use App\Modules\Partner\Enums\PartnerAccessEventType;
use App\Modules\Partner\Services\LedgerService;
use Carbon\CarbonImmutable;

/*
 * The ledger's pure maths (rnc-2 R11, plan AM9): the commission of one closing, half-up to the
 * cent, and the partners whose portal access existed on any day of a month, folded from the
 * grant/revoke rows. The month here is November 2026 in São Paulo.
 */
function ledgerUnitAt(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'America/Sao_Paulo')->utc();
}

/**
 * @param  list<array{0: int, 1: string, 2: string}>  $events  [partner_id, granted|revoked, moment]
 * @return list<int>
 */
function ledgerUnitSubscribed(array $events): array
{
    $rows = array_map(fn (array $event) => ['partner_id' => $event[0], 'type' => PartnerAccessEventType::from($event[1]), 'at' => ledgerUnitAt($event[2])], $events);

    return LedgerService::subscribedPartnerIds($rows, ledgerUnitAt('2026-11-01 00:00'), ledgerUnitAt('2026-11-30 23:59:59'));
}

describe('commission', function () {
    it('is the rate in basis points of the value, half-up to the cent (AM9)', function (int $cents, int $rateBp, int $expected) {
        expect(LedgerService::commission($cents, $rateBp))->toBe($expected);
    })->with([
        'R$ 12.000 at 10 %' => [1_200_000, 1000, 120_000],
        'R$ 12.345,67 at 10 % → R$ 1.234,57' => [1_234_567, 1000, 123_457],
        'R$ 12.345,64 at 10 % → R$ 1.234,56' => [1_234_564, 1000, 123_456],
        'exactly half a cent rounds up' => [5, 1000, 1],
        'under half a cent rounds down' => [4, 1000, 0],
        'R$ 1.000 at 12,5 %' => [100_000, 1250, 12_500],
        'nothing at 0 %' => [1_234_567, 0, 0],
    ]);
});

describe('subscribed partners', function () {
    it('bills a partner granted before the month and never revoked', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-09-10 09:00']]))->toBe([1]);
    });

    it('bills a partner granted during the month', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-11-30 23:00']]))->toBe([1]);
    });

    it('bills a partner revoked during the month', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-09-10 09:00'], [1, 'revoked', '2026-11-01 08:00']]))->toBe([1]);
    });

    it('does not bill a partner revoked before the month', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-09-10 09:00'], [1, 'revoked', '2026-10-31 23:59']]))->toBe([]);
    });

    it('bills the first period of a revoke and a regrant (AM9)', function () {
        $events = [[1, 'granted', '2026-11-02 09:00'], [1, 'revoked', '2026-11-03 09:00'], [1, 'granted', '2026-12-10 09:00']];

        expect(ledgerUnitSubscribed($events))->toBe([1]);
    });

    it('bills a partner revoked before the month and regranted in it', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-08-01 09:00'], [1, 'revoked', '2026-08-30 09:00'], [1, 'granted', '2026-11-20 09:00']]))->toBe([1]);
    });

    it('does not bill a grant after the month', function () {
        expect(ledgerUnitSubscribed([[1, 'granted', '2026-12-01 00:00']]))->toBe([]);
    });

    it('folds each partner on its own, in partner order', function () {
        $events = [
            [3, 'granted', '2026-10-01 09:00'],
            [2, 'granted', '2026-10-01 09:00'],
            [2, 'revoked', '2026-10-15 09:00'],
            [1, 'granted', '2026-11-05 09:00'],
        ];

        expect(ledgerUnitSubscribed($events))->toBe([1, 3]);
    });

    it('ignores a revoke with no grant before it', function () {
        expect(ledgerUnitSubscribed([[1, 'revoked', '2026-11-05 09:00']]))->toBe([]);
    });
});
