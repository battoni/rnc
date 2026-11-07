<?php

namespace App\Modules\Fiscal\Repositories;

use App\Modules\Fiscal\DTOs\PurchaseTotals;
use App\Modules\Fiscal\DTOs\ReadinessCounts;
use App\Modules\Fiscal\Enums\NfeDirection;
use App\Modules\Fiscal\Models\NfeDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The firm's NF-e documents and their raw files. The row and the file are written together: a file
 * that cannot be written rolls the row back, so no row points at nothing. The file lives on the
 * private `local` disk, never `public` (R16, D7, A15).
 */
class NfeDocumentRepository
{
    public const DISK = 'local';

    private const DIRECTORY = 'nfe';

    private const EXTENSION = '.xml';

    private const UTC_FORMAT = 'Y-m-d H:i:s';

    public function existsNfeDocument(string $accessKey, int $clientId): bool
    {
        return NfeDocument::query()->where('access_key', $accessKey)->where('client_id', $clientId)->exists();
    }

    /**
     * The window's issued invoices (saída) counted per client AND emitting system — ONE aggregate
     * query for the whole firm, whatever the number of clients (plan AM6); the service adds the
     * systems up per client and picks the most frequent. Complete = no item failing any check; at
     * risk = an item without the `IBSCBS` group. Portable SQL (`CASE`, `SUM`, `COUNT`), bounds as
     * UTC strings because the column is UTC and the app runs in São Paulo time.
     *
     * @return Collection<int, ReadinessCounts>
     */
    public function sumNfeDocumentReadinessByClient(CarbonImmutable $from, CarbonImmutable $to, ?int $clientId = null): Collection
    {
        return NfeDocument::query()
            ->toBase()
            ->select('client_id', 'system')
            ->selectRaw('COUNT(*) AS invoices')
            ->selectRaw('SUM(CASE WHEN items_missing_group = 0 AND items_missing_codes = 0 AND items_without_test_values = 0 THEN 1 ELSE 0 END) AS complete')
            ->selectRaw('SUM(CASE WHEN items_missing_group > 0 THEN 1 ELSE 0 END) AS at_risk')
            ->where('direction', NfeDirection::Saida->value)
            ->whereBetween('issued_at', self::utcBounds($from, $to))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->groupBy('client_id', 'system')
            ->get()
            ->map(fn (object $row) => new ReadinessCounts(
                clientId: (int) $row->client_id,
                system: $row->system === null ? null : (string) $row->system,
                invoices: (int) $row->invoices,
                complete: (int) $row->complete,
                atRisk: (int) $row->at_risk,
            ));
    }

    /**
     * One client's incomplete issued invoices of the window, newest first — the detail's issue lines
     * and samples (R19). Bounded by one client's month of sales.
     *
     * @return Collection<int, NfeDocument>
     */
    public function findIncompleteNfeDocumentsByClient(int $clientId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return NfeDocument::query()
            ->select('id', 'number', 'series', 'issued_at', 'items_missing_group', 'items_missing_codes', 'items_without_test_values', 'sample')
            ->where('client_id', $clientId)
            ->where('direction', NfeDirection::Saida->value)
            ->whereBetween('issued_at', self::utcBounds($from, $to))
            ->where(fn ($query) => $query->where('items_missing_group', '>', 0)->orWhere('items_missing_codes', '>', 0)->orWhere('items_without_test_values', '>', 0))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * The window's purchases (entrada) per client: documents, value and the value bought from
     * suppliers whose CRT is one of `$simplesCrts` — ONE aggregate query for the whole firm (AM6).
     *
     * @param  list<int>  $simplesCrts
     * @return Collection<int, PurchaseTotals>
     */
    public function sumNfeDocumentPurchasesByClient(CarbonImmutable $from, CarbonImmutable $to, array $simplesCrts, ?int $clientId = null): Collection
    {
        $placeholders = implode(', ', array_fill(0, count($simplesCrts), '?'));

        return NfeDocument::query()
            ->toBase()
            ->select('client_id')
            ->selectRaw('COUNT(*) AS documents')
            ->selectRaw('SUM(total_cents) AS purchases_cents')
            ->selectRaw("SUM(CASE WHEN emitter_crt IN ({$placeholders}) THEN total_cents ELSE 0 END) AS simples_cents", $simplesCrts)
            ->where('direction', NfeDirection::Entrada->value)
            ->whereBetween('issued_at', self::utcBounds($from, $to))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->groupBy('client_id')
            ->get()
            ->map(fn (object $row) => new PurchaseTotals(
                clientId: (int) $row->client_id,
                documents: (int) $row->documents,
                purchasesCents: (int) $row->purchases_cents,
                simplesCents: (int) $row->simples_cents,
            ));
    }

    /**
     * The path comes only from integers and the validated 44-digit key — never from the uploaded
     * file's name (plan AM3).
     */
    public static function nfeDocumentPath(int $firmId, int $clientId, string $accessKey): string
    {
        return self::DIRECTORY.'/'.$firmId.'/'.$clientId.'/'.$accessKey.self::EXTENSION;
    }

    /**
     * Throws `UniqueConstraintViolationException` when the client already holds the key — a race the
     * importer answers as `duplicate`.
     *
     * @param  array<string, mixed>  $attributes  every column but `path`
     */
    public function createNfeDocument(array $attributes, string $path, string $content): NfeDocument
    {
        return DB::transaction(function () use ($attributes, $path, $content) {
            $document = NfeDocument::query()->create([...$attributes, 'path' => $path]);

            if (! Storage::disk(self::DISK)->put($path, $content)) {
                throw new RuntimeException("The NF-e file {$path} could not be written.");
            }

            return $document;
        });
    }

    /** @return array{0: string, 1: string} the window as the UTC strings `issued_at` holds */
    private static function utcBounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->utc()->format(self::UTC_FORMAT), $to->utc()->format(self::UTC_FORMAT)];
    }
}
