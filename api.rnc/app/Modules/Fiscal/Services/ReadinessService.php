<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Fiscal\DTOs\ReadinessCounts;
use App\Modules\Fiscal\DTOs\ReadinessFilterDTO;
use App\Modules\Fiscal\Enums\ReadinessIssue;
use App\Modules\Fiscal\Enums\ReadinessStatus;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Portfolio\Enums\ClientEventType;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientEvent;
use App\Modules\Portfolio\Repositories\ClientRepository;
use App\Modules\Portfolio\Services\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Prontidão da NF-e" (rnc-2 R18–R20, A16). Presumido and Real clients (Simples is out until 2027,
 * facts.md §2) and their issued invoices of the last 30 days: how many, how many complete, how many
 * at risk of rejection 1115, the most frequent emitting system and the status
 * (`ReadinessStatus`). The list reads the clients in one query and the documents in ONE aggregate
 * grouped by client (plan AM6); every sum, percentage, status and order is pure PHP here.
 *
 * @phpstan-type ReadinessRow array{client: Client, system: string|null, invoices: int, complete: int, complete_pct: float|null, at_risk: int, status: ReadinessStatus}
 * @phpstan-type ReadinessSample array{number: int, series: int, item: int|null}
 * @phpstan-type ReadinessIssueLine array{code: string, invoices: int, risk: bool, samples: list<ReadinessSample>}
 */
class ReadinessService
{
    public const WINDOW_DAYS = 30;

    public const REGIMES = [Regime::Presumido->value, Regime::Real->value];

    public const SAMPLES = 5;

    public function __construct(
        private NfeDocumentRepository $nfeDocumentRepository,
        private ClientRepository $clientRepository,
        private ClientService $clientService,
    ) {}

    /**
     * @return array{summary: array{clients: int, invoices: int, complete: int, complete_pct: float|null, at_risk: int, by_status: array<string, int>}, systems: list<string>, clients: list<ReadinessRow>}
     */
    public function list(ReadinessFilterDTO $filter): array
    {
        [$from, $to] = self::window();
        $groups = $this->nfeDocumentRepository->sumNfeDocumentReadinessByClient($from, $to)->groupBy(fn (ReadinessCounts $counts) => $counts->clientId);
        $rows = $this->clientRepository->findClientsByRegimes(self::REGIMES)
            ->map(fn (Client $client) => self::row($client, $groups->get($client->id, collect())))
            ->sort(self::compareRows(...))
            ->values();

        $listed = $rows->filter(function (array $row) use ($filter) {
            $statusMatches = $filter->status === null || $row['status']->value === $filter->status;
            $systemMatches = $filter->system === null || $row['system'] === $filter->system;

            return $statusMatches && $systemMatches;
        });

        return [
            'summary' => self::summary($rows),
            'systems' => $rows->pluck('system')->filter()->unique()->sort()->values()->all(),
            'clients' => $listed->values()->all(),
        ];
    }

    /**
     * One client's row and its issue lines, each with up to five sample invoices newest first.
     * Null when the client is not the firm's or is not Presumido/Real.
     *
     * @return array{row: ReadinessRow, issues: list<ReadinessIssueLine>}|null
     */
    public function show(int $clientId): ?array
    {
        $client = $this->findListedClient($clientId);

        if ($client === null) {
            return null;
        }

        [$from, $to] = self::window();
        $row = self::row($client, $this->nfeDocumentRepository->sumNfeDocumentReadinessByClient($from, $to, $client->id));

        return ['row' => $row, 'issues' => self::issues($this->nfeDocumentRepository->findIncompleteNfeDocumentsByClient($client->id, $from, $to))];
    }

    /**
     * "Registrar aviso" (R20): one `nfe.aviso` line on the client's timeline, with who and the
     * counts the e-mail text carried. Nothing is sent (D12). Null when the client is not listed.
     */
    public function notice(int $clientId, int $userId): ?ClientEvent
    {
        $client = $this->findListedClient($clientId);

        if ($client === null) {
            return null;
        }

        [$from, $to] = self::window();
        $row = self::row($client, $this->nfeDocumentRepository->sumNfeDocumentReadinessByClient($from, $to, $client->id));

        return $this->clientService->recordEvent($client, ClientEventType::NfeNotice, [
            'invoices' => $row['invoices'],
            'complete_pct' => $row['complete_pct'],
            'at_risk' => $row['at_risk'],
            'status' => $row['status']->value,
        ], $userId);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the last 30 days, up to now */
    private static function window(): array
    {
        $now = CarbonImmutable::now();

        return [$now->subDays(self::WINDOW_DAYS), $now];
    }

    private function findListedClient(int $clientId): ?Client
    {
        $client = $this->clientRepository->findClientById($clientId);
        $isListed = $client !== null && in_array($client->regime, self::REGIMES, true);

        return $isListed ? $client : null;
    }

    /**
     * @param  Collection<int, ReadinessCounts>  $groups  one client's groups, one per system
     * @return ReadinessRow
     */
    private static function row(Client $client, Collection $groups): array
    {
        $invoices = (int) $groups->sum(fn (ReadinessCounts $counts) => $counts->invoices);
        $complete = (int) $groups->sum(fn (ReadinessCounts $counts) => $counts->complete);
        $atRisk = (int) $groups->sum(fn (ReadinessCounts $counts) => $counts->atRisk);

        return [
            'client' => $client,
            'system' => self::mostFrequentSystem($groups),
            'invoices' => $invoices,
            'complete' => $complete,
            'complete_pct' => self::percent($complete, $invoices),
            'at_risk' => $atRisk,
            'status' => ReadinessStatus::fromCounts($invoices, $complete, $atRisk),
        ];
    }

    /**
     * The `ide/verProc` on most of the client's invoices; a tie goes to the name first in
     * alphabetical order, so the answer never depends on the database's row order. A blank
     * `verProc` (stored null) never wins over a named one.
     *
     * @param  Collection<int, ReadinessCounts>  $groups
     */
    private static function mostFrequentSystem(Collection $groups): ?string
    {
        $named = $groups->filter(fn (ReadinessCounts $counts) => $counts->system !== null)
            ->sort(fn (ReadinessCounts $a, ReadinessCounts $b) => $b->invoices <=> $a->invoices ?: strcmp($a->system ?? '', $b->system ?? ''));

        return $named->first()?->system;
    }

    /** One decimal; null with no invoice (the screen shows "—"). */
    private static function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : null;
    }

    /**
     * Status order, then most at risk, then least complete (compared as exact fractions), then id
     * — the demo's `nfeClients` sort, `sem_notas` last.
     *
     * @param  ReadinessRow  $a
     * @param  ReadinessRow  $b
     */
    private static function compareRows(array $a, array $b): int
    {
        return $a['status']->order() <=> $b['status']->order()
            ?: $b['at_risk'] <=> $a['at_risk']
            ?: $a['complete'] * $b['invoices'] <=> $b['complete'] * $a['invoices']
            ?: $a['client']->id <=> $b['client']->id;
    }

    /**
     * Over EVERY listed client, whatever the filter: the hero's at-risk total and the KPIs, the %
     * weighted by the invoices received (Σ complete ÷ Σ invoices, demo `filledN / received`).
     *
     * @param  Collection<int, ReadinessRow>  $rows
     * @return array{clients: int, invoices: int, complete: int, complete_pct: float|null, at_risk: int, by_status: array<string, int>}
     */
    private static function summary(Collection $rows): array
    {
        $invoices = (int) $rows->sum('invoices');
        $complete = (int) $rows->sum('complete');
        $byStatus = [];

        foreach (ReadinessStatus::cases() as $status) {
            $byStatus[$status->value] = $rows->filter(fn (array $row) => $row['status'] === $status)->count();
        }

        return [
            'clients' => $rows->count(),
            'invoices' => $invoices,
            'complete' => $complete,
            'complete_pct' => self::percent($complete, $invoices),
            'at_risk' => (int) $rows->sum('at_risk'),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Each incomplete invoice under its worst issue (`ReadinessIssue` order), counted once; up to
     * five samples per line naming the invoice and the first item that failed (Fiscal W55).
     *
     * @param  Collection<int, NfeDocument>  $documents  newest first
     * @return list<ReadinessIssueLine>
     */
    private static function issues(Collection $documents): array
    {
        $lines = [];

        foreach ($documents as $document) {
            $issue = self::worstIssue($document);
            $lines[$issue->value] ??= ['code' => $issue->value, 'invoices' => 0, 'risk' => $issue->isRisk(), 'samples' => []];
            $lines[$issue->value]['invoices']++;

            if (count($lines[$issue->value]['samples']) < self::SAMPLES) {
                $lines[$issue->value]['samples'][] = [
                    'number' => (int) $document->number,
                    'series' => (int) $document->series,
                    'item' => $document->sample[$issue->value][0] ?? null,
                ];
            }
        }

        $ordered = [];

        foreach (ReadinessIssue::cases() as $issue) {
            if (isset($lines[$issue->value])) {
                $ordered[] = $lines[$issue->value];
            }
        }

        return $ordered;
    }

    private static function worstIssue(NfeDocument $document): ReadinessIssue
    {
        foreach (ReadinessIssue::cases() as $issue) {
            if ((int) $document->getAttribute($issue->column()) > 0) {
                return $issue;
            }
        }

        return ReadinessIssue::WithoutTestValues;
    }
}
