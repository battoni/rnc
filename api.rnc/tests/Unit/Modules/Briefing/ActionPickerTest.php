<?php

use App\Modules\Briefing\Helpers\ActionPicker;

/*
 * A22 (rnc-3 R25): "As 3 ações de hoje" are the first three that apply, in the demo's order
 * (`ceo.js` → `topActions`): a breached first-contact SLA, today's meetings, the open proposals with
 * their range, the partial leads. What does not apply is left out — no breached SLA, and the list
 * starts at the meetings. Each action names its kind, the numbers its sentence needs (the sentence
 * itself is the screen's and the e-mail's, in their own language) and the screen it opens.
 *
 * The Radar's opportunity (R25's fifth) waits for rnc-2's Radar on this branch (state.md W81).
 */
function briefingFacts(array $overrides = []): array
{
    return [
        'breached' => ['partner_id' => 7, 'partner' => 'Andrade Advocacia', 'company' => 'Padaria Sol', 'hours' => 31.5],
        'meetings_today' => 2,
        'proposals' => ['count' => 3, 'range_cents' => [5400000, 10800000]],
        'partial_leads' => 4,
        ...$overrides,
    ];
}

describe('ActionPicker (A22)', function () {
    it('keeps the demo order and stops at three', function () {
        $actions = ActionPicker::pick(briefingFacts());

        expect(array_column($actions, 'kind'))->toBe(['sla_breached', 'meetings_today', 'proposals'])
            ->and(count($actions))->toBe(ActionPicker::MAX);
    });

    it('names the partner, the company and the hours of the breached SLA, against the 24-hour goal, and opens that partner’s trail', function () {
        expect(ActionPicker::pick(briefingFacts())[0])->toBe([
            'kind' => 'sla_breached',
            'params' => ['partner' => 'Andrade Advocacia', 'company' => 'Padaria Sol', 'hours' => 31.5, 'goal_hours' => 24],
            'to' => '/reinaldo/trilha?parceiro=7',
        ]);
    });

    it('starts at the meetings when no SLA is breached', function () {
        $actions = ActionPicker::pick(briefingFacts(['breached' => null]));

        expect(array_column($actions, 'kind'))->toBe(['meetings_today', 'proposals', 'partial_leads'])
            ->and($actions[0])->toBe(['kind' => 'meetings_today', 'params' => ['count' => 2], 'to' => '/equipe/agenda'])
            ->and($actions[1])->toBe(['kind' => 'proposals', 'params' => ['count' => 3, 'range_cents' => [5400000, 10800000]], 'to' => '/equipe/pipeline?etapa=proposta'])
            ->and($actions[2])->toBe(['kind' => 'partial_leads', 'params' => ['count' => 4], 'to' => '/equipe/pipeline?etapa=lead_parcial']);
    });

    it('leaves out every action that does not apply, keeping the order of the rest', function () {
        $actions = ActionPicker::pick(briefingFacts(['meetings_today' => 0, 'proposals' => ['count' => 0, 'range_cents' => [0, 0]]]));

        expect(array_column($actions, 'kind'))->toBe(['sla_breached', 'partial_leads']);
    });

    it('answers no action on a quiet day', function () {
        expect(ActionPicker::pick(briefingFacts([
            'breached' => null,
            'meetings_today' => 0,
            'proposals' => ['count' => 0, 'range_cents' => null],
            'partial_leads' => 0,
        ])))->toBe([]);
    });

    it('keeps a proposal action without a range on an empty portfolio', function () {
        $actions = ActionPicker::pick(briefingFacts(['breached' => null, 'meetings_today' => 0, 'proposals' => ['count' => 1, 'range_cents' => null]]));

        expect($actions[0])->toBe(['kind' => 'proposals', 'params' => ['count' => 1, 'range_cents' => null], 'to' => '/equipe/pipeline?etapa=proposta']);
    });
});
