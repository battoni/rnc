<?php

namespace App\Modules\Radar\Services;

use App\Modules\Diagnostic\Enums\DiagnosticKind;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Fiscal\DTOs\SalesTotals;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Repositories\ClientRepository;

/**
 * "Radar da carteira" (rnc-2 R24–R26; the demo's `radarSummary`): the rules engine over EVERY
 * client of the firm, each read with its XML evidence (`EvidenceBuilder`). One row per rule type
 * found, opportunities first, each biggest range first; the total adds opportunities only (risks
 * never add money). An item's range is the engine's (band revenue × the rule's share, a year), in
 * integer cents — except `split-payment`, whose range is R23's cash held per month
 * (`SplitPaymentCalculator`), so its row is Σ of the held ranges (R26, A19).
 *
 * Fixed cost (plan AM6): the clients (one query), the sales aggregate (one), and for a type the
 * keys of its clients (one); everything else is pure PHP.
 *
 * @phpstan-type Item array{client: Client, rule: DiagnosticRule, range_cents: array{0: int, 1: int}, totals: SalesTotals|null}
 * @phpstan-type TypeRow array{rule: string, kind: string, title: string, citation: string, clients: int, without_consent: int, range_cents: array{0: int, 1: int}, period: string}
 */
class RadarService
{
    public const PERIOD_YEAR = 'ano';

    public const PERIOD_MONTH = 'mes';

    private const CENTS = 100;

    public function __construct(
        private ClientRepository $clientRepository,
        private EvidenceBuilder $evidenceBuilder,
        private RulesEngine $rulesEngine,
    ) {}

    /**
     * @return array{clients: int, with_opportunity: int, total_cents: array{0: int, 1: int}, risk_types: int, types: list<TypeRow>}
     */
    public function summary(): array
    {
        $clients = $this->clientRepository->findAllClients();
        $items = $this->items($clients->all(), $this->evidenceBuilder->salesByClient());

        $types = [];

        foreach (self::groupByRule($items) as $value => $ruleItems) {
            $types[] = self::typeRow(DiagnosticRule::from($value), $ruleItems);
        }

        usort($types, self::compareTypes(...));

        $opportunities = array_values(array_filter($types, fn (array $type) => $type['kind'] === DiagnosticKind::Opportunity->value));
        $withOpportunity = [];

        foreach ($items as $item) {
            if ($item['rule']->kind() === DiagnosticKind::Opportunity) {
                $withOpportunity[$item['client']->id] = true;
            }
        }

        return [
            'clients' => $clients->count(),
            'with_opportunity' => count($withOpportunity),
            'total_cents' => [
                array_sum(array_map(fn (array $type) => $type['range_cents'][0], $opportunities)),
                array_sum(array_map(fn (array $type) => $type['range_cents'][1], $opportunities)),
            ],
            'risk_types' => count($types) - count($opportunities),
            'types' => $types,
        ];
    }

    /**
     * One rule type with its clients, biggest range first (ties by id), each with its range, its
     * proof (R25) and up to three access keys when the proof is the XMLs.
     *
     * @return array{type: array<string, mixed>, clients: list<array{client: Client, range_cents: array{0: int, 1: int}, evidence: array<string, mixed>, keys: list<string>}>}
     */
    public function type(DiagnosticRule $rule): array
    {
        $totals = $this->evidenceBuilder->salesByClient();
        $items = array_values(array_filter(
            $this->items($this->clientRepository->findAllClients()->all(), $totals),
            fn (array $item) => $item['rule'] === $rule,
        ));
        usort($items, fn (array $a, array $b) => $b['range_cents'][1] <=> $a['range_cents'][1] ?: $a['client']->id <=> $b['client']->id);

        $rows = [];
        $fromNotes = [];

        foreach ($items as $item) {
            $evidence = EvidenceBuilder::proof($rule, $item['client'], $item['totals']);
            $rows[] = ['client' => $item['client'], 'range_cents' => $item['range_cents'], 'evidence' => $evidence];

            if ($evidence['source'] === EvidenceBuilder::SOURCE_NOTES) {
                $fromNotes[] = $item['client']->id;
            }
        }

        $keys = $this->evidenceBuilder->keysByClient($rule, $fromNotes);

        return [
            'type' => [...self::typeRow($rule, $items), 'detail' => $rule->detail()],
            'clients' => array_map(fn (array $row) => [...$row, 'keys' => $keys[$row['client']->id] ?? []], $rows),
        ];
    }

    /**
     * Every client's diagnostic items, read with its evidence.
     *
     * @param  list<Client>  $clients
     * @param  array<int, SalesTotals>  $totals
     * @return list<Item>
     */
    private function items(array $clients, array $totals): array
    {
        $items = [];

        foreach ($clients as $client) {
            $clientTotals = $totals[$client->id] ?? null;
            $diagnosis = $this->rulesEngine->diagnose(EvidenceBuilder::profile($client, $clientTotals));

            foreach ($diagnosis['items'] as $item) {
                $rule = DiagnosticRule::from($item['id']);
                $range = $rule === DiagnosticRule::SplitPayment
                    ? EvidenceBuilder::split($client, $clientTotals)['held_per_month_cents']
                    : [self::cents($item['min']), self::cents($item['max'])];

                $items[] = ['client' => $client, 'rule' => $rule, 'range_cents' => $range, 'totals' => $clientTotals];
            }
        }

        return $items;
    }

    /**
     * @param  list<Item>  $items
     * @return array<string, list<Item>>
     */
    private static function groupByRule(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[$item['rule']->value][] = $item;
        }

        return $groups;
    }

    /**
     * @param  list<Item>  $items  one rule's
     * @return TypeRow
     */
    private static function typeRow(DiagnosticRule $rule, array $items): array
    {
        return [
            'rule' => $rule->value,
            'kind' => $rule->kind()->value,
            'title' => $rule->title(),
            'citation' => $rule->citation(),
            'clients' => count($items),
            'without_consent' => count(array_filter($items, fn (array $item) => $item['client']->partner_consent !== true)),
            'range_cents' => [
                array_sum(array_map(fn (array $item) => $item['range_cents'][0], $items)),
                array_sum(array_map(fn (array $item) => $item['range_cents'][1], $items)),
            ],
            'period' => $rule === DiagnosticRule::SplitPayment ? self::PERIOD_MONTH : self::PERIOD_YEAR,
        ];
    }

    /**
     * Opportunities first, then the biggest range's top, then the rules engine's own order.
     *
     * @param  TypeRow  $a
     * @param  TypeRow  $b
     */
    private static function compareTypes(array $a, array $b): int
    {
        $order = array_map(fn (DiagnosticRule $rule) => $rule->value, DiagnosticRule::cases());
        $isOpportunity = fn (array $type) => $type['kind'] === DiagnosticKind::Opportunity->value;

        return $isOpportunity($b) <=> $isOpportunity($a)
            ?: $b['range_cents'][1] <=> $a['range_cents'][1]
            ?: array_search($a['rule'], $order, true) <=> array_search($b['rule'], $order, true);
    }

    /** The engine's reais to integer cents, half-up. */
    private static function cents(float $reais): int
    {
        return (int) round($reais * self::CENTS);
    }
}
