<?php

namespace App\Modules\Radar\Services;

use App\Models\User;
use App\Modules\Diagnostic\Enums\DiagnosticKind;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Fiscal\DTOs\SalesTotals;
use App\Modules\Partner\Services\ReferralService;
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
 * Each row also carries its partner picker (R27, F10): the active partners whose specialty covers
 * the rule, with the next batch's size and how many were already sent (Partner's
 * `portfolioBatchOptions`), and the batch itself is Partner's `referBatch` over the row's clients
 * in this order.
 *
 * Fixed cost (plan AM6): the clients (one query), the sales aggregate (one), the pickers (three),
 * and for a type the keys of its clients (one); everything else is pure PHP.
 *
 * @phpstan-type Item array{client: Client, rule: DiagnosticRule, range_cents: array{0: int, 1: int}, totals: SalesTotals|null}
 * @phpstan-type PartnerOption array{id: int, name: string, specialty: string, batch: int, sent: int}
 * @phpstan-type TypeRow array{rule: string, kind: string, title: string, citation: string, clients: int, without_consent: int, range_cents: array{0: int, 1: int}, period: string, partners: list<PartnerOption>}
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
        private ReferralService $referralService,
    ) {}

    /**
     * @return array{clients: int, with_opportunity: int, total_cents: array{0: int, 1: int}, risk_types: int, types: list<TypeRow>}
     */
    public function summary(): array
    {
        $clients = $this->clientRepository->findAllClients();
        $items = $this->items($clients->all(), $this->evidenceBuilder->salesByClient());

        $groups = array_map(self::ranked(...), self::groupByRule($items));
        $options = $this->referralService->portfolioBatchOptions(array_map(self::clientsOf(...), $groups));
        $types = [];

        foreach ($groups as $value => $ruleItems) {
            $types[] = self::typeRow(DiagnosticRule::from($value), $ruleItems, $options[$value]);
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
        $items = $this->itemsOf($rule, $this->evidenceBuilder->salesByClient());
        $options = $this->referralService->portfolioBatchOptions([$rule->value => self::clientsOf($items)]);

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
            'type' => [...self::typeRow($rule, $items, $options[$rule->value]), 'detail' => $rule->detail()],
            'clients' => array_map(fn (array $row) => [...$row, 'keys' => $keys[$row['client']->id] ?? []], $rows),
        ];
    }

    /**
     * "Encaminhar os N maiores" (R27): the rule's clients in this screen's order, each with the
     * profile the Radar read, handed to Partner, which picks the batch and writes it.
     *
     * @return array{sent: int, skipped_no_consent: int, skipped_already: int}
     */
    public function referBatch(DiagnosticRule $rule, int $partnerId, User $by): array
    {
        $ranked = array_map(
            fn (array $item) => ['client' => $item['client'], 'profile' => EvidenceBuilder::profile($item['client'], $item['totals'])],
            $this->itemsOf($rule, $this->evidenceBuilder->salesByClient()),
        );

        return $this->referralService->referBatch($rule, $partnerId, $ranked, $by);
    }

    /**
     * One rule's items over every client, biggest range first (ties by id).
     *
     * @param  array<int, SalesTotals>  $totals
     * @return list<Item>
     */
    private function itemsOf(DiagnosticRule $rule, array $totals): array
    {
        return self::ranked(array_values(array_filter(
            $this->items($this->clientRepository->findAllClients()->all(), $totals),
            fn (array $item) => $item['rule'] === $rule,
        )));
    }

    /**
     * @param  list<Item>  $items
     * @return list<Item> the range's top first, ties by client id
     */
    private static function ranked(array $items): array
    {
        usort($items, fn (array $a, array $b) => $b['range_cents'][1] <=> $a['range_cents'][1] ?: $a['client']->id <=> $b['client']->id);

        return $items;
    }

    /**
     * @param  list<Item>  $items
     * @return list<Client>
     */
    private static function clientsOf(array $items): array
    {
        return array_map(fn (array $item) => $item['client'], $items);
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
     * @param  list<PartnerOption>  $partners
     * @return TypeRow
     */
    private static function typeRow(DiagnosticRule $rule, array $items, array $partners): array
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
            'partners' => $partners,
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
