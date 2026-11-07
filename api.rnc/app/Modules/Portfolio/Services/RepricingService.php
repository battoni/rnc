<?php

namespace App\Modules\Portfolio\Services;

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Portfolio\DTOs\ClientQuoteDTO;
use App\Modules\Portfolio\DTOs\MarkRepricingDTO;
use App\Modules\Portfolio\DTOs\RepricingFilterDTO;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Repositories\ClientRepository;
use Carbon\CarbonImmutable;

/**
 * Fee repricing over the firm's portfolio (rnc-1 R26, A14, the demo's `repricingView()`): every
 * client quoted by `RepricingCalculator`, the yearly uplift over the filtered list, the spread by
 * percent band, one client's items, its letter, and the batch mark that copies the quote onto the
 * client with who and when.
 *
 * The quote is computed on every read, not stored: it follows the rules engine and the client's
 * profile as they are today. Only the mark is a snapshot (`{at, by, pct, suggested}`).
 */
class RepricingService
{
    /** The spread's bands of percent, as the demo draws them (`RP_BUCKETS`). */
    public const SPREAD_BANDS = [[4, 8], [9, 13], [14, 19], [20, 25]];

    /** The Reforma's first year with CBS: the letter's new fee applies from its first day. */
    public const EFFECTIVE_FROM = '2027-01-01';

    private const MONTHS_IN_YEAR = 12;

    private const DECIMALS = 1;

    public function __construct(
        private ClientRepository $clientRepository,
        private RepricingCalculator $calculator,
        private CurrentFirm $currentFirm,
    ) {}

    /**
     * @return array{summary: array{clients: int, current_yearly: float, uplift_total: float, uplift_share: float, average_pct: float, marked: int}, spread: list<array{from: int, to: int, count: int}>, clients: list<ClientQuoteDTO>}
     */
    public function list(RepricingFilterDTO $filter): array
    {
        $quotes = $this->quoteAll($this->clientRepository->findClientsByProfile($filter->sector, $filter->regime)->all());

        usort($quotes, fn (ClientQuoteDTO $first, ClientQuoteDTO $second) => $second->quote['uplift'] <=> $first->quote['uplift'] ?: strcmp($first->client->name, $second->client->name));

        $currentYearly = array_sum(array_map(fn (ClientQuoteDTO $row) => $row->quote['current'] * self::MONTHS_IN_YEAR, $quotes));
        $upliftTotal = array_sum(array_map(fn (ClientQuoteDTO $row) => $row->quote['uplift'], $quotes));
        $pcts = array_map(fn (ClientQuoteDTO $row) => $row->quote['pct'], $quotes);

        return [
            'summary' => [
                'clients' => count($quotes),
                'current_yearly' => round($currentYearly, 2),
                'uplift_total' => round($upliftTotal, 2),
                'uplift_share' => $currentYearly > 0 ? round($upliftTotal / $currentYearly * 100, self::DECIMALS) : 0.0,
                'average_pct' => $pcts !== [] ? round(array_sum($pcts) / count($pcts), self::DECIMALS) : 0.0,
                'marked' => $this->clientRepository->countMarkedClients(),
            ],
            'spread' => array_map(fn (array $band) => [
                'from' => $band[0],
                'to' => $band[1],
                'count' => count(array_filter($pcts, fn (int $pct) => $pct >= $band[0] && $pct <= $band[1])),
            ], self::SPREAD_BANDS),
            'clients' => $quotes,
        ];
    }

    public function show(int $id): ?ClientQuoteDTO
    {
        $client = $this->clientRepository->findClientById($id);

        return $client ? $this->quoteAll([$client])[0] : null;
    }

    /** @return array{quote: ClientQuoteDTO, firm: Firm, issued_on: string, effective_from: string}|null */
    public function letter(int $id): ?array
    {
        $quote = $this->show($id);
        $firm = $this->currentFirm->get();

        if (! $quote || ! $firm) {
            return null;
        }

        return [
            'quote' => $quote,
            'firm' => $firm,
            'issued_on' => CarbonImmutable::now()->toDateString(),
            'effective_from' => self::EFFECTIVE_FROM,
        ];
    }

    /** @return list<ClientQuoteDTO> the marked clients as they are now */
    public function mark(MarkRepricingDTO $dto, User $user): array
    {
        $at = CarbonImmutable::now()->utc()->toIso8601String();

        foreach ($this->clientRepository->findClientsByIds($dto->client_ids) as $client) {
            $quote = $this->calculator->quote($client->profile(), $client->fee());

            $this->clientRepository->markClientRepricing($client, ['at' => $at, 'by' => $user->id, 'pct' => $quote['pct'], 'suggested' => $quote['suggested']]);
        }

        return $this->quoteAll($this->clientRepository->findClientsByIds($dto->client_ids)->all());
    }

    /**
     * @param  list<Client>  $clients
     * @return list<ClientQuoteDTO>
     */
    private function quoteAll(array $clients): array
    {
        $markerIds = array_values(array_unique(array_filter(array_map(fn (Client $client) => $client->repricing['by'] ?? null, $clients))));
        $markerNames = $markerIds === [] ? [] : $this->clientRepository->findUserNamesByIds($markerIds);

        return array_map(fn (Client $client) => new ClientQuoteDTO(
            client: $client,
            quote: $this->calculator->quote($client->profile(), $client->fee()),
            markedByName: $markerNames[$client->repricing['by'] ?? 0] ?? null,
        ), $clients);
    }
}
