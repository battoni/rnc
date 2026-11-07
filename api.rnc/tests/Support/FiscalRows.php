<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;

/**
 * The Fiscal analytics tests' rows (rnc-2 F7): clients, people and parsed NF-e rows written
 * straight to the tables, so a test states the counts it reads (A16, A18) without building XMLs —
 * the parser and the importer have their own tests (F6a, F6b). Every row carries its firm.
 */
final class FiscalRows
{
    private static int $sequence = 0;

    public static function francoId(): int
    {
        return (int) Firm::where('slug', 'franco')->value('id');
    }

    public static function person(string $role, ?int $firmId = null): User
    {
        return User::factory()->create([
            'firm_id' => $firmId ?? self::francoId(),
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    public static function client(array $attributes = [], ?int $firmId = null): Client
    {
        self::$sequence++;

        return Client::withoutGlobalScopes()->create([
            'firm_id' => $firmId ?? self::francoId(),
            'name' => 'Cliente '.self::$sequence.' Ltda',
            'cnpj' => str_pad((string) self::$sequence, 14, '0', STR_PAD_LEFT),
            'sector' => 'comercio',
            'regime' => 'presumido',
            'band' => 'b3',
            'b2b_share' => 70,
            'monthly_fee' => 3000,
            ...$attributes,
        ]);
    }

    /**
     * `$count` issued invoices (saída) of one kind, issued `$daysAgo` days ago.
     * `$kind`: `complete`, `missing_group`, `missing_codes`, `without_test_values`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function sales(Client $client, int $count, string $kind = 'complete', int $daysAgo = 5, array $attributes = []): void
    {
        foreach (range(1, $count) as $ignored) {
            self::document($client, 'saida', [
                'items' => 3,
                'items_missing_group' => $kind === 'missing_group' ? 1 : 0,
                'items_missing_codes' => $kind === 'missing_codes' ? 2 : 0,
                'items_without_test_values' => $kind === 'without_test_values' ? 1 : 0,
                'sample' => [
                    'missing_group' => $kind === 'missing_group' ? [2] : [],
                    'missing_codes' => $kind === 'missing_codes' ? [1, 3] : [],
                    'without_test_values' => $kind === 'without_test_values' ? [3] : [],
                ],
                'issued_at' => CarbonImmutable::now()->subDays($daysAgo),
                ...$attributes,
            ]);
        }
    }

    /**
     * One purchase (entrada) of `$totalCents` from a supplier with `$crt`.
     */
    public static function purchase(Client $client, int $totalCents, ?int $crt, int $daysAgo = 10): NfeDocument
    {
        return self::document($client, 'entrada', [
            'total_cents' => $totalCents,
            'emitter_crt' => $crt,
            'emitter_cnpj' => '99800000000191',
            'recipient_cnpj' => $client->cnpj,
            'issued_at' => CarbonImmutable::now()->subDays($daysAgo),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    public static function document(Client $client, string $direction, array $attributes = []): NfeDocument
    {
        self::$sequence++;
        $import = NfeImport::withoutGlobalScopes()->forceCreate([
            'firm_id' => $client->firm_id,
            'user_id' => null,
            'source' => 'folder',
            'files' => 1,
            'stored' => 1,
            'refused' => [],
            'at' => CarbonImmutable::now(),
        ]);

        return NfeDocument::withoutGlobalScopes()->forceCreate([
            'firm_id' => $client->firm_id,
            'client_id' => $client->id,
            'import_id' => $import->id,
            'direction' => $direction,
            'access_key' => str_pad((string) self::$sequence, 44, '0', STR_PAD_LEFT),
            'number' => self::$sequence,
            'series' => 1,
            'issued_at' => CarbonImmutable::now()->subDays(5),
            'emitter_cnpj' => $direction === 'saida' ? $client->cnpj : '99800000000191',
            'emitter_crt' => 3,
            'recipient_cnpj' => '99700000000191',
            'recipient_kind' => 'cnpj',
            'total_cents' => 100_000,
            'system' => 'TOTVS Protheus 12.1',
            'items' => 3,
            'items_missing_group' => 0,
            'items_missing_codes' => 0,
            'items_without_test_values' => 0,
            'sample' => ['missing_group' => [], 'missing_codes' => [], 'without_test_values' => []],
            'path' => 'nfe/test.xml',
            ...$attributes,
        ]);
    }
}
