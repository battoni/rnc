<?php

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Diagnostic\Services\RulesEngine;
use Tests\TestCase;

uses(TestCase::class);

/*
 * R12 (rnc-1 F3): the demo's `diagnose()` rule table, ported item by item. Each rule cites its line
 * of the facts.md §2 statement table and scales an R$ range from the faturamento band's
 * representative revenue (b1 240 mil · b2 1,8 mi · b3 12 mi · b4 90 mi · b5 450 mi).
 */
function ruleProfile(array $overrides = []): DiagnosticProfileDTO
{
    return DiagnosticProfileDTO::from(array_merge([
        'sector' => 'comercio',
        'regime' => 'presumido',
        'band' => 'b2',
        'b2b' => 30,
        'incentivos' => false,
        'exporta' => false,
    ], $overrides));
}

/** @return list<string> */
function ruleIds(DiagnosticProfileDTO $profile): array
{
    return array_column((new RulesEngine)->diagnose($profile)['items'], 'id');
}

describe('each rule of the table', function () {
    it('applies where its line of the table says, and nowhere else', function (string $rule, array $applies, array $doesNotApply) {
        expect(ruleIds(ruleProfile($applies)))->toContain($rule)
            ->and(ruleIds(ruleProfile($doesNotApply)))->not->toContain($rule);
    })->with([
        'simples-regular: Simples with B2B ≥ 50 %' => ['simples-regular', ['regime' => 'simples', 'b2b' => 50], ['regime' => 'simples', 'b2b' => 40]],
        'simples-credito: Simples with B2B ≥ 50 %' => ['simples-credito', ['regime' => 'simples', 'b2b' => 80], ['regime' => 'presumido', 'b2b' => 80]],
        'simples-b2c: Simples with B2B < 50 %' => ['simples-b2c', ['regime' => 'simples', 'b2b' => 20], ['regime' => 'simples', 'b2b' => 50]],
        'simples-b2c: an unanswered B2B share counts as B2C' => ['simples-b2c', ['regime' => 'simples', 'b2b' => null], ['regime' => 'real', 'b2b' => null]],
        'erp-nfe: Presumido' => ['erp-nfe', ['regime' => 'presumido'], ['regime' => 'simples']],
        'erp-nfe: Real' => ['erp-nfe', ['regime' => 'real'], ['regime' => 'nao_sei']],
        'pis-cofins-fim: Presumido or Real' => ['pis-cofins-fim', ['regime' => 'real'], ['regime' => 'simples']],
        'servicos-30: serviços' => ['servicos-30', ['sector' => 'servicos'], ['sector' => 'saude']],
        'reducao-60: saúde' => ['reducao-60', ['sector' => 'saude'], ['sector' => 'servicos']],
        'reducao-60: educação' => ['reducao-60', ['sector' => 'educacao'], ['sector' => 'agro']],
        'agro-60: agro' => ['agro-60', ['sector' => 'agro'], ['sector' => 'industria']],
        'agro-in-natura: agro' => ['agro-in-natura', ['sector' => 'agro'], ['sector' => 'comercio']],
        'icms-incentivos: ICMS incentives' => ['icms-incentivos', ['incentivos' => true], ['incentivos' => false]],
        'exportacao: exporters' => ['exportacao', ['exporta' => true], ['exporta' => false]],
    ]);

    it('always lists the contracts review and the split payment risk', function () {
        foreach (['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'] as $sector) {
            expect(ruleIds(ruleProfile(['sector' => $sector])))->toContain('contratos', 'split-payment');
        }
    });

    it('carries the kind, the title, the cited rule and the detail of every rule', function () {
        foreach (DiagnosticRule::cases() as $rule) {
            expect($rule->kind()->value)->toBeIn(['oportunidade', 'risco'])
                ->and($rule->title())->not->toBeEmpty()
                ->and($rule->citation())->not->toBeEmpty()
                ->and($rule->detail())->not->toBeEmpty();
        }

        expect(DiagnosticRule::cases())->toHaveCount(13);
    });
});

describe('a diagnosis', function () {
    it('gives "Simples, B2B 80 %, serviços" the regular-regime option and at least three opportunities (A7)', function () {
        $diagnosis = (new RulesEngine)->diagnose(ruleProfile(['regime' => 'simples', 'b2b' => 80, 'sector' => 'servicos']));

        expect(array_column($diagnosis['items'], 'id'))->toBe(['simples-regular', 'simples-credito', 'servicos-30', 'contratos', 'split-payment'])
            ->and($diagnosis['opportunities'])->toBe(3)
            ->and($diagnosis['risks'])->toBe(2);
    });

    it('scales each range from the band revenue', function () {
        $items = collect((new RulesEngine)->diagnose(ruleProfile(['regime' => 'simples', 'b2b' => 80, 'band' => 'b2']))['items'])->keyBy('id');

        // simples-regular is 1–3 % of R$ 1,8 mi.
        expect($items['simples-regular']['min'])->toEqual(18000.0)
            ->and($items['simples-regular']['max'])->toEqual(54000.0)
            ->and($items['simples-regular']['kind'])->toBe('oportunidade')
            ->and($items['simples-regular']['title'])->toBe('Opção pelo regime regular do IBS/CBS');
    });

    it('is empty until sector, regime and band are known', function () {
        $diagnosis = (new RulesEngine)->diagnose(ruleProfile(['band' => null]));

        expect($diagnosis)->toBe(['items' => [], 'opportunities' => 0, 'risks' => 0]);
    });
});

describe('the R$ range label (formatRange)', function () {
    it('writes thousands as "mil" and millions as "mi"', function (array $range, string $unit, string $label) {
        expect(RulesEngine::formatRange($range[0], $range[1], $unit))->toBe($label);
    })->with([
        [[18000, 54000], 'ano', 'R$ 18–54 mil/ano'],
        [[240, 960], 'ano', 'R$ 1–2 mil/ano'],
        [[1_200_000, 3_400_000], 'ano', 'R$ 1,2–3,4 mi/ano'],
        [[450_000, 1_350_000], 'ano', 'R$ 0,5–1,4 mi/ano'],
        [[15_900, 27_825], 'mês', 'R$ 16–28 mil/mês'],
        [[1_500_000, 2_250_000], 'ano', 'R$ 1,5–2,3 mi/ano'],
        [[1_234_000, 1_234_567_000], 'ano', 'R$ 1,2–1.234,6 mi/ano'],
    ]);
});

describe('the impact simulator (taxMix, A8)', function () {
    it('returns the eight years 2026–2033', function () {
        expect(array_column((new RulesEngine)->taxMix(ruleProfile()), 'year'))->toBe([2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033]);
    });

    it('has no PIS/Cofins, ICMS or ISS in 2033', function () {
        foreach (['comercio', 'servicos', 'saude', 'agro'] as $sector) {
            $year2033 = collect((new RulesEngine)->taxMix(ruleProfile(['sector' => $sector])))->firstWhere('year', 2033);

            expect(array_column($year2033['parts'], 'tax'))->toBe(['cbs', 'ibs']);
        }
    });

    it('keeps the demo rates for a commerce company on Lucro Presumido', function () {
        $years = collect((new RulesEngine)->taxMix(ruleProfile(['sector' => 'comercio', 'regime' => 'presumido'])))->keyBy('year');

        expect($years[2026]['parts'])->toBe([
            ['tax' => 'pis-cofins', 'label' => 'PIS/Cofins', 'rate' => 3.7],
            ['tax' => 'icms', 'label' => 'ICMS', 'rate' => 18.0],
            ['tax' => 'cbs', 'label' => 'CBS', 'rate' => 0.9],
            ['tax' => 'ibs', 'label' => 'IBS', 'rate' => 0.1],
        ])
            ->and($years[2026]['total'])->toBe(22.7)
            ->and($years[2027]['parts'])->toBe([
                ['tax' => 'icms', 'label' => 'ICMS', 'rate' => 18.0],
                ['tax' => 'cbs', 'label' => 'CBS', 'rate' => 11.2],
                ['tax' => 'ibs', 'label' => 'IBS', 'rate' => 0.1],
            ])
            ->and($years[2030]['parts'])->toBe([
                ['tax' => 'icms', 'label' => 'ICMS', 'rate' => 14.4],
                ['tax' => 'cbs', 'label' => 'CBS', 'rate' => 11.3],
                ['tax' => 'ibs', 'label' => 'IBS', 'rate' => 3.0],
            ])
            ->and($years[2033]['total'])->toBe(26.5);
    });

    it('charges ISS instead of ICMS to services, PIS/Cofins at 9,25 % on Lucro Real, and 40 % of IBS/CBS to saúde', function () {
        $services = collect((new RulesEngine)->taxMix(ruleProfile(['sector' => 'servicos', 'regime' => 'real'])))->keyBy('year');
        $health = collect((new RulesEngine)->taxMix(ruleProfile(['sector' => 'saude'])))->keyBy('year');

        expect(array_column($services[2026]['parts'], 'rate', 'tax'))->toBe(['pis-cofins' => 9.3, 'iss' => 5.0, 'cbs' => 0.9, 'ibs' => 0.1])
            ->and(array_column($health[2033]['parts'], 'rate', 'tax'))->toBe(['cbs' => 4.5, 'ibs' => 6.1]);
    });
});

describe('the split payment cash effect', function () {
    it('is 40–70 % of a month of IBS/CBS on the band revenue', function () {
        [$min, $max] = (new RulesEngine)->splitPaymentRange(ruleProfile(['band' => 'b2']));

        // R$ 1,8 mi / 12 × 26,5 % = R$ 39.750 a month.
        expect(round($min, 2))->toBe(15900.0)
            ->and(round($max, 2))->toBe(27825.0);
    });

    it('takes 40 % of it for the reduced sectors', function () {
        [$min] = (new RulesEngine)->splitPaymentRange(ruleProfile(['band' => 'b2', 'sector' => 'educacao']));

        expect(round($min, 2))->toBe(6360.0);
    });
});
