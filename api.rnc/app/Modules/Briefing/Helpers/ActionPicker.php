<?php

namespace App\Modules\Briefing\Helpers;

use App\Modules\Partner\Services\ReferralSla;

/**
 * "As 3 ações de hoje" (rnc-3 R25; the demo's `topActions`, `ceo.js`): the first three that apply,
 * most urgent first — chase the partner of the longest breached first-contact SLA, prepare today's
 * meetings, close the open proposals (with their annual range), call the partial leads. What does
 * not apply is left out, so a day without a breached SLA starts at the meetings.
 *
 * Each action is `{kind, params, to}`: the numbers its sentence needs and the screen it opens. The
 * sentence is the reader's — the SPA's locale and the e-mail's `lang/*\/mail.php` — so the API never
 * writes copy of its own (rnc-3 W82). The fifth is the Radar's first opportunity type (rnc-3 F6b,
 * W81): its rule, title, clients and range, opening the Radar. Pure: the caller hands it the day's figures.
 *
 * @phpstan-type Breached array{partner_id: int, partner: string, company: string, hours: float}
 * @phpstan-type Radar array{rule: string, title: string, clients: int, range_cents: array{0: int, 1: int}, period: string}
 * @phpstan-type Facts array{breached: Breached|null, meetings_today: int, proposals: array{count: int, range_cents: array{0: int, 1: int}|null}, partial_leads: int, radar: Radar|null}
 * @phpstan-type Action array{kind: string, params: array<string, mixed>, to: string}
 */
class ActionPicker
{
    public const MAX = 3;

    public const SLA_BREACHED = 'sla_breached';

    public const MEETINGS_TODAY = 'meetings_today';

    public const PROPOSALS = 'proposals';

    public const PARTIAL_LEADS = 'partial_leads';

    public const RADAR = 'radar';

    /**
     * @param  Facts  $facts
     * @return list<Action>
     */
    public static function pick(array $facts): array
    {
        $actions = [];
        $breached = $facts['breached'];

        if ($breached !== null) {
            $actions[] = [
                'kind' => self::SLA_BREACHED,
                'params' => ['partner' => $breached['partner'], 'company' => $breached['company'], 'hours' => $breached['hours'], 'goal_hours' => ReferralSla::HOURS],
                'to' => '/gestao/trilha?parceiro='.$breached['partner_id'],
            ];
        }

        if ($facts['meetings_today'] > 0) {
            $actions[] = ['kind' => self::MEETINGS_TODAY, 'params' => ['count' => $facts['meetings_today']], 'to' => '/equipe/agenda'];
        }

        if ($facts['proposals']['count'] > 0) {
            $actions[] = ['kind' => self::PROPOSALS, 'params' => $facts['proposals'], 'to' => '/equipe/pipeline?etapa=proposta'];
        }

        if ($facts['partial_leads'] > 0) {
            $actions[] = ['kind' => self::PARTIAL_LEADS, 'params' => ['count' => $facts['partial_leads']], 'to' => '/equipe/pipeline?etapa=lead_parcial'];
        }

        if ($facts['radar'] !== null) {
            $actions[] = ['kind' => self::RADAR, 'params' => $facts['radar'], 'to' => '/gestao/radar'];
        }

        return array_slice($actions, 0, self::MAX);
    }
}
