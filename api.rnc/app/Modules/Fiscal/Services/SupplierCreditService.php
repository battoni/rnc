<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\DTOs\PurchaseTotals;
use App\Modules\Fiscal\DTOs\SupplierCreditFilterDTO;
use App\Modules\Fiscal\Exceptions\FiscalException;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Portfolio\Enums\ClientEventType;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientEvent;
use App\Modules\Portfolio\Repositories\ClientRepository;
use App\Modules\Portfolio\Services\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Crédito de fornecedores" (rnc-2 R21–R22, A18). Presumido and Real clients with purchase invoices
 * (entrada) in the last 90 days: purchases a month, the share bought from Simples/MEI suppliers by
 * value, and the credit lost per year from 2027 (`SupplierCreditCalculator`, a range). The list
 * reads the clients, the purchases (ONE aggregate grouped by client — plan AM6) and the clients
 * with a proposed review, three queries whatever the portfolio's size; the maths is pure PHP.
 *
 * @phpstan-type SupplierCreditRow array{client: Client, documents: int, monthly_purchases_cents: int, simples_share: float, normal_share: float, lost_per_year_cents: array{0: int, 1: int}, proposed: bool}
 */
class SupplierCreditService
{
    public const WINDOW_DAYS = 90;

    public function __construct(
        private NfeDocumentRepository $nfeDocumentRepository,
        private ClientRepository $clientRepository,
        private ClientService $clientService,
    ) {}

    /**
     * Biggest range first (the top of it, then the bottom), then id; the KPIs over exactly the
     * filtered rows (demo `suppliersView`).
     *
     * @return array{summary: array{clients: int, buying_from_simples: int, mean_simples_share: float, lost_per_year_cents: array{0: int, 1: int}, proposed: int}, clients: list<SupplierCreditRow>}
     */
    public function list(SupplierCreditFilterDTO $filter): array
    {
        [$from, $to] = self::window();
        $purchases = $this->nfeDocumentRepository
            ->sumNfeDocumentPurchasesByClient($from, $to, SupplierCreditCalculator::SIMPLES_CRTS)
            ->keyBy(fn (PurchaseTotals $totals) => $totals->clientId);
        $proposed = array_flip($this->clientService->clientIdsWithEvent(ClientEventType::SupplierReview));

        $rows = $this->clientRepository->findClientsByRegimes(ReadinessService::REGIMES, $filter->sector)
            ->filter(fn (Client $client) => $purchases->has($client->id))
            ->map(fn (Client $client) => self::row($client, $purchases->get($client->id), isset($proposed[$client->id])))
            ->sort(fn (array $a, array $b) => $b['lost_per_year_cents'][1] <=> $a['lost_per_year_cents'][1]
                ?: $b['lost_per_year_cents'][0] <=> $a['lost_per_year_cents'][0]
                ?: $a['client']->id <=> $b['client']->id)
            ->values();

        return ['summary' => self::summary($rows), 'clients' => $rows->all()];
    }

    /**
     * "Propor revisão" (R22): one `fornecedores.revisao` line on the client's timeline with who and
     * the figures it was proposed on. Refused (422 `no_simples_purchases`) when nothing in the
     * window was bought from Simples — the button is disabled there. Null when the client is not
     * the firm's or not Presumido/Real.
     */
    public function review(int $clientId, int $userId): ?ClientEvent
    {
        $client = $this->clientRepository->findClientById($clientId);
        $isListed = $client !== null && in_array($client->regime, ReadinessService::REGIMES, true);

        if (! $isListed) {
            return null;
        }

        [$from, $to] = self::window();
        $purchases = $this->nfeDocumentRepository
            ->sumNfeDocumentPurchasesByClient($from, $to, SupplierCreditCalculator::SIMPLES_CRTS, $client->id)
            ->first();

        if ($purchases === null || $purchases->simplesCents === 0) {
            throw FiscalException::noSimplesPurchases();
        }

        $row = self::row($client, $purchases, false);

        return $this->clientService->recordEvent($client, ClientEventType::SupplierReview, [
            'simples_share' => $row['simples_share'],
            'lost_per_year_cents' => $row['lost_per_year_cents'],
        ], $userId);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the last 90 days, up to now */
    private static function window(): array
    {
        $now = CarbonImmutable::now();

        return [$now->subDays(self::WINDOW_DAYS), $now];
    }

    /**
     * @return SupplierCreditRow
     */
    private static function row(Client $client, PurchaseTotals $purchases, bool $proposed): array
    {
        $estimate = SupplierCreditCalculator::estimate($purchases->purchasesCents, $purchases->simplesCents);

        return [
            'client' => $client,
            'documents' => $purchases->documents,
            'monthly_purchases_cents' => $estimate['monthly_purchases_cents'],
            'simples_share' => $estimate['simples_share'],
            'normal_share' => round(100 - $estimate['simples_share'], 1),
            'lost_per_year_cents' => $estimate['lost_per_year_cents'],
            'proposed' => $proposed,
        ];
    }

    /**
     * @param  Collection<int, SupplierCreditRow>  $rows
     * @return array{clients: int, buying_from_simples: int, mean_simples_share: float, lost_per_year_cents: array{0: int, 1: int}, proposed: int}
     */
    private static function summary(Collection $rows): array
    {
        $buying = $rows->filter(fn (array $row) => $row['simples_share'] > 0);

        return [
            'clients' => $rows->count(),
            'buying_from_simples' => $buying->count(),
            'mean_simples_share' => $buying->isEmpty() ? 0.0 : round((float) $buying->avg('simples_share'), 1),
            'lost_per_year_cents' => [
                (int) $rows->sum(fn (array $row) => $row['lost_per_year_cents'][0]),
                (int) $rows->sum(fn (array $row) => $row['lost_per_year_cents'][1]),
            ],
            'proposed' => $rows->filter(fn (array $row) => $row['proposed'])->count(),
        ];
    }
}
