<?php

namespace App\Modules\Radar\Services;

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Fiscal\DTOs\SalesKey;
use App\Modules\Fiscal\DTOs\SalesTotals;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Fiscal\Services\ReadinessService;
use App\Modules\Fiscal\Services\SplitPaymentCalculator;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The Radar's XML evidence (rnc-2 R25, D9). The facts come from ONE aggregate over the firm's
 * issued invoices grouped by client (plan AM6), and the access keys from one more query when a
 * type is opened; everything else is pure PHP here.
 *
 * - The B2B share is measured BY VALUE (sales to a CNPJ ÷ all sales, half-up to a whole percent)
 *   and replaces the declared one only from `MIN_DOCUMENTS` issued invoices in the 90 days (D9).
 * - Each rule the XMLs speak to carries its proof: `erp-nfe` the readiness counts of the last 30
 *   days (Fiscal's window), `simples-*` the measured share and the document count, `split-payment`
 *   R23's held range — from the invoices whenever the client issued any in the 90 days (Fiscal W80:
 *   none to a CNPJ is zero held, not the cadastro). Every other item is "do cadastro".
 * - Up to `KEYS` access keys per client, the rule's own kind of invoice first, newest first.
 *
 * @phpstan-type Proof array<string, string|int|array{0: int, 1: int}>
 */
class EvidenceBuilder
{
    /** D9: the measured share stands from this many issued invoices in the window. */
    public const MIN_DOCUMENTS = 10;

    /** plan Defaults: the purchases and B2B window. */
    public const WINDOW_DAYS = 90;

    public const KEYS = 3;

    public const SOURCE_NOTES = 'notas';

    public const SOURCE_CADASTRO = 'cadastro';

    private const SIMPLES_RULES = [DiagnosticRule::SimplesRegular, DiagnosticRule::SimplesCredito, DiagnosticRule::SimplesB2c];

    private const PERCENT = 100;

    public function __construct(private NfeDocumentRepository $nfeDocumentRepository) {}

    /**
     * The firm's sales facts per client, keyed by client id; a client with no issued invoice in the
     * window is absent. ONE query.
     *
     * @return array<int, SalesTotals>
     */
    public function salesByClient(): array
    {
        [$from, $recentFrom, $to] = self::windows();

        return $this->nfeDocumentRepository->sumNfeDocumentSalesByClient($from, $recentFrom, $to)
            ->keyBy(fn (SalesTotals $totals) => $totals->clientId)
            ->all();
    }

    /**
     * Up to three access keys per client for one rule's proof. ONE query; none for no client.
     *
     * @param  list<int>  $clientIds
     * @return array<int, list<string>>
     */
    public function keysByClient(DiagnosticRule $rule, array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        [$from, , $to] = self::windows();

        return self::pickKeys($rule, $this->nfeDocumentRepository->findNfeDocumentSalesKeysByClients($clientIds, $from, $to));
    }

    /** D9: null below `MIN_DOCUMENTS` or when the invoices add up to nothing. */
    public static function measuredB2bShare(?SalesTotals $totals): ?int
    {
        $isEnough = $totals !== null && $totals->documents >= self::MIN_DOCUMENTS && $totals->salesCents > 0;

        if (! $isEnough) {
            return null;
        }

        return intdiv($totals->b2bCents * self::PERCENT * 2 + $totals->salesCents, $totals->salesCents * 2);
    }

    /** What the rules engine reads: the cadastro (`Client::profile()`), with the measured share when D9 applies. */
    public static function profile(Client $client, ?SalesTotals $totals): DiagnosticProfileDTO
    {
        $profile = $client->profile();
        $measured = self::measuredB2bShare($totals);

        return $measured === null ? $profile : new DiagnosticProfileDTO(
            sector: $profile->sector,
            regime: $profile->regime,
            band: $profile->band,
            b2b: $measured,
            incentivos: $profile->incentivos,
            exporta: $profile->exporta,
        );
    }

    /**
     * R23's held range for one client: from its sales to a CNPJ when it issued any invoice in the
     * window, else from the cadastro.
     *
     * @return array{basis: string, monthly_b2b_cents: int, held_per_month_cents: array{0: int, 1: int}}
     */
    public static function split(Client $client, ?SalesTotals $totals): array
    {
        $hasSales = $totals !== null && $totals->documents > 0;

        return SplitPaymentCalculator::estimate($hasSales ? $totals->b2bCents : null, $client->band, $client->b2b_share);
    }

    /**
     * The proof an item shows (R25). `source` is `notas` or `cadastro` ("do cadastro").
     *
     * @return Proof
     */
    public static function proof(DiagnosticRule $rule, Client $client, ?SalesTotals $totals): array
    {
        if ($rule === DiagnosticRule::SplitPayment) {
            $split = self::split($client, $totals);
            $fromNotes = $split['basis'] === SplitPaymentCalculator::BASIS_NOTES;

            return [
                'source' => $fromNotes ? self::SOURCE_NOTES : self::SOURCE_CADASTRO,
                ...($fromNotes ? ['window_days' => self::WINDOW_DAYS] : []),
                'monthly_b2b_cents' => $split['monthly_b2b_cents'],
                'held_per_month_cents' => $split['held_per_month_cents'],
            ];
        }

        if ($rule === DiagnosticRule::ErpNfe && $totals !== null && $totals->recentInvoices > 0) {
            return [
                'source' => self::SOURCE_NOTES,
                'window_days' => ReadinessService::WINDOW_DAYS,
                'invoices' => $totals->recentInvoices,
                'complete' => $totals->recentComplete,
                'at_risk' => $totals->recentAtRisk,
            ];
        }

        $measured = self::measuredB2bShare($totals);

        if (in_array($rule, self::SIMPLES_RULES, true) && $measured !== null) {
            return [
                'source' => self::SOURCE_NOTES,
                'window_days' => self::WINDOW_DAYS,
                'documents' => $totals->documents,
                'b2b_share' => $measured,
                'declared_b2b_share' => $client->b2b_share,
            ];
        }

        return ['source' => self::SOURCE_CADASTRO];
    }

    /**
     * Per client, up to `KEYS` keys: the rule's own kind of invoice first — `erp-nfe` the incomplete
     * ones of the 30 days (and only the 30 days), `simples-b2c` sales to consumers, every other rule
     * sales to a company — then the rest, newest first within each.
     *
     * @param  Collection<int, SalesKey>  $rows
     * @return array<int, list<string>>
     */
    public static function pickKeys(DiagnosticRule $rule, Collection $rows): array
    {
        $recentFrom = CarbonImmutable::now()->subDays(ReadinessService::WINDOW_DAYS);
        $isReadiness = $rule === DiagnosticRule::ErpNfe;
        $keys = [];

        $ranked = $rows
            ->filter(fn (SalesKey $row) => ! $isReadiness || $row->issuedAt->greaterThanOrEqualTo($recentFrom))
            ->sort(function (SalesKey $a, SalesKey $b) use ($rule) {
                return self::isPreferred($rule, $b) <=> self::isPreferred($rule, $a)
                    ?: $b->issuedAt->getTimestamp() <=> $a->issuedAt->getTimestamp();
            });

        foreach ($ranked as $row) {
            if (count($keys[$row->clientId] ?? []) < self::KEYS) {
                $keys[$row->clientId][] = $row->accessKey;
            }
        }

        return $keys;
    }

    private static function isPreferred(DiagnosticRule $rule, SalesKey $row): bool
    {
        return match ($rule) {
            DiagnosticRule::ErpNfe => $row->incomplete,
            DiagnosticRule::SimplesB2c => ! $row->toCompany,
            default => $row->toCompany,
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable} the 90 days, the 30 days' start, now */
    private static function windows(): array
    {
        $now = CarbonImmutable::now();

        return [$now->subDays(self::WINDOW_DAYS), $now->subDays(ReadinessService::WINDOW_DAYS), $now];
    }
}
