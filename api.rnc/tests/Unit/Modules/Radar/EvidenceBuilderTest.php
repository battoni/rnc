<?php

use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Fiscal\DTOs\SalesKey;
use App\Modules\Fiscal\DTOs\SalesTotals;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Radar\Services\EvidenceBuilder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The Radar's XML evidence (rnc-2 F9, R25, D9, A20's unit half; plan AM6). Pure maths over ONE
 * aggregate of the client's issued invoices: the B2B share measured by value replaces the declared
 * one only from 10 sales documents in the 90 days (D9); each rule tied to the XMLs carries its
 * proof (`erp-nfe` the readiness counts of the 30 days, `simples-*` the measured share and the
 * document count, `split-payment` R23's held range); anything else is "do cadastro". Up to three
 * access keys per client.
 */
function evidenceClient(array $attributes = []): Client
{
    return (new Client)->forceFill([
        'id' => 7,
        'name' => 'Teste Ltda',
        'cnpj' => '11222333000181',
        'sector' => 'servicos',
        'regime' => 'simples',
        'band' => 'b2',
        'b2b_share' => 20,
        ...$attributes,
    ]);
}

function evidenceTotals(int $documents, int $salesCents, int $b2bCents, int $recentInvoices = 0, int $recentComplete = 0, int $recentAtRisk = 0): SalesTotals
{
    return new SalesTotals(7, $documents, $salesCents, $b2bCents, $recentInvoices, $recentComplete, $recentAtRisk);
}

function evidenceKey(int $clientId, string $digit, bool $toCompany, int $daysAgo, bool $incomplete = false): SalesKey
{
    return new SalesKey($clientId, str_repeat($digit, 44), $toCompany, CarbonImmutable::now()->subDays($daysAgo), $incomplete);
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
});

describe('the measured B2B share (D9)', function () {
    it('needs 10 sales documents in the window: 9 keep the declared share', function () {
        expect(EvidenceBuilder::measuredB2bShare(evidenceTotals(9, 1_000, 900)))->toBeNull()
            ->and(EvidenceBuilder::measuredB2bShare(evidenceTotals(10, 1_000, 800)))->toBe(80)
            ->and(EvidenceBuilder::measuredB2bShare(null))->toBeNull()
            ->and(EvidenceBuilder::MIN_DOCUMENTS)->toBe(10);
    });

    it('is the share by value, half-up to a whole percent', function () {
        // 2 of 3 by value = 66,67 % → 67; 1 of 8 = 12,5 % → 13.
        expect(EvidenceBuilder::measuredB2bShare(evidenceTotals(12, 300, 200)))->toBe(67)
            ->and(EvidenceBuilder::measuredB2bShare(evidenceTotals(12, 800, 100)))->toBe(13)
            ->and(EvidenceBuilder::measuredB2bShare(evidenceTotals(12, 800, 0)))->toBe(0);
    });

    it('keeps the declared share when the documents add up to nothing', function () {
        expect(EvidenceBuilder::measuredB2bShare(evidenceTotals(12, 0, 0)))->toBeNull();
    });

    it('puts the measured share into the profile the rules engine reads, the rest from the cadastro', function () {
        $client = evidenceClient();

        expect(EvidenceBuilder::profile($client, evidenceTotals(12, 1_000, 800))->toArray())->toMatchArray(['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false])
            ->and(EvidenceBuilder::profile($client, evidenceTotals(9, 1_000, 800))->b2b)->toBe(20)
            ->and(EvidenceBuilder::profile($client, null)->b2b)->toBe(20);
    });
});

describe('the proof of each rule (R25)', function () {
    it('proves the simples rules with the measured share and the document count', function () {
        $proof = EvidenceBuilder::proof(DiagnosticRule::SimplesRegular, evidenceClient(), evidenceTotals(12, 1_000, 800));

        expect($proof)->toBe(['source' => 'notas', 'window_days' => 90, 'documents' => 12, 'b2b_share' => 80, 'declared_b2b_share' => 20])
            ->and(EvidenceBuilder::proof(DiagnosticRule::SimplesB2c, evidenceClient(), evidenceTotals(9, 1_000, 100)))->toBe(['source' => 'cadastro']);
    });

    it('proves erp-nfe with the readiness counts of the 30 days, or the cadastro with none', function () {
        $client = evidenceClient(['regime' => 'presumido']);

        expect(EvidenceBuilder::proof(DiagnosticRule::ErpNfe, $client, evidenceTotals(3, 1_000, 1_000, 2, 1, 1)))
            ->toBe(['source' => 'notas', 'window_days' => 30, 'invoices' => 2, 'complete' => 1, 'at_risk' => 1])
            ->and(EvidenceBuilder::proof(DiagnosticRule::ErpNfe, $client, evidenceTotals(3, 1_000, 1_000)))->toBe(['source' => 'cadastro']);
    });

    it('proves split-payment with R23 held range: das notas from any issued invoice, else the cadastro (W80)', function () {
        $client = evidenceClient(['band' => 'b3', 'b2b_share' => 70]);

        expect(EvidenceBuilder::proof(DiagnosticRule::SplitPayment, $client, evidenceTotals(3, 30_000_000, 30_000_000)))
            ->toBe(['source' => 'notas', 'window_days' => 90, 'monthly_b2b_cents' => 10_000_000, 'held_per_month_cents' => [1_060_000, 1_590_000]])
            // Sales, none to a company: the XML says no B2B, so zero held — not the cadastro.
            ->and(EvidenceBuilder::proof(DiagnosticRule::SplitPayment, $client, evidenceTotals(2, 500_000, 0))['held_per_month_cents'])->toBe([0, 0])
            ->and(EvidenceBuilder::proof(DiagnosticRule::SplitPayment, $client, null))
            ->toBe(['source' => 'cadastro', 'monthly_b2b_cents' => 70_000_000, 'held_per_month_cents' => [7_420_000, 11_130_000]]);
    });

    it('says "do cadastro" for every rule the XMLs say nothing about', function (DiagnosticRule $rule) {
        expect(EvidenceBuilder::proof($rule, evidenceClient(), evidenceTotals(40, 1_000, 900, 10, 10, 0)))->toBe(['source' => 'cadastro']);
    })->with(fn () => array_values(array_filter(DiagnosticRule::cases(), fn (DiagnosticRule $rule) => ! in_array($rule, [
        DiagnosticRule::SimplesRegular, DiagnosticRule::SimplesCredito, DiagnosticRule::SimplesB2c, DiagnosticRule::ErpNfe, DiagnosticRule::SplitPayment,
    ], true))));
});

describe('the access keys (R25: up to three per client)', function () {
    it('gives a B2B rule its newest sales to a company first, three at most, per client', function () {
        $rows = collect([
            evidenceKey(7, '1', true, 2),
            evidenceKey(7, '2', false, 1),
            evidenceKey(7, '3', true, 5),
            evidenceKey(7, '4', true, 9),
            evidenceKey(7, '5', true, 20),
            evidenceKey(8, '6', false, 3),
        ]);

        expect(EvidenceBuilder::pickKeys(DiagnosticRule::SimplesRegular, $rows))->toBe([
            7 => [str_repeat('1', 44), str_repeat('3', 44), str_repeat('4', 44)],
            // No sale to a company: the newest sale stands in.
            8 => [str_repeat('6', 44)],
        ]);
    });

    it('gives simples-b2c its sales to consumers first', function () {
        $rows = collect([evidenceKey(7, '1', true, 1), evidenceKey(7, '2', false, 4)]);

        expect(EvidenceBuilder::pickKeys(DiagnosticRule::SimplesB2c, $rows))->toBe([7 => [str_repeat('2', 44), str_repeat('1', 44)]]);
    });

    it('gives erp-nfe the incomplete invoices of the 30 days first, never an older one', function () {
        $rows = collect([
            evidenceKey(7, '1', true, 2),
            evidenceKey(7, '2', true, 6, incomplete: true),
            evidenceKey(7, '3', true, 45, incomplete: true),
        ]);

        expect(EvidenceBuilder::pickKeys(DiagnosticRule::ErpNfe, $rows))->toBe([7 => [str_repeat('2', 44), str_repeat('1', 44)]]);
    });
});

describe('one aggregate per call (plan AM6)', function () {
    it('asks the repository once for the whole firm and keys the totals by client', function () {
        $repository = Mockery::mock(NfeDocumentRepository::class);
        $repository->shouldReceive('sumNfeDocumentSalesByClient')->once()->andReturn(collect([
            new SalesTotals(7, 12, 1_000, 800, 0, 0, 0),
            new SalesTotals(9, 3, 500, 0, 1, 1, 0),
        ]));

        $totals = (new EvidenceBuilder($repository))->salesByClient();

        expect(array_keys($totals))->toBe([7, 9])
            ->and($totals[7]->b2bCents)->toBe(800);
    });

    it('asks the repository once for the keys of every listed client', function () {
        $repository = Mockery::mock(NfeDocumentRepository::class);
        $repository->shouldReceive('findNfeDocumentSalesKeysByClients')->once()->withArgs(fn (array $ids) => $ids === [7, 8])->andReturn(collect([evidenceKey(7, '1', true, 1)]));

        expect((new EvidenceBuilder($repository))->keysByClient(DiagnosticRule::SplitPayment, [7, 8]))->toBe([7 => [str_repeat('1', 44)]])
            ->and((new EvidenceBuilder(Mockery::mock(NfeDocumentRepository::class)))->keysByClient(DiagnosticRule::SplitPayment, []))->toBe([]);
    });
});
