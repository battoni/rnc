<?php

namespace App\Modules\Portfolio\Services;

use App\Modules\Portfolio\Helpers\ClientCsvParser;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Repositories\ClientRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The portfolio's CSV import (rnc-1 R25, A13): all or nothing. The parser refuses the file whole
 * before anything is written; the writes run in one transaction, so a failure half-way leaves the
 * portfolio as it was. A CNPJ the firm already has is updated in place (its repricing mark stays:
 * it is the record of what was proposed then).
 *
 * The partner consent (rnc-2 R28) is written only when the file has the `autoriza_parceiros`
 * column: an answer is stamped `via = contrato` with the moment of the import, an unchanged answer
 * keeps the moment it was first given, and an empty cell clears all three.
 */
class ClientImportService
{
    public function __construct(
        private ClientCsvParser $parser,
        private ClientRepository $clientRepository,
    ) {}

    /** @return array{created: int, updated: int, total: int} */
    public function import(string $content): array
    {
        $rows = $this->parser->parse($content);

        $current = $this->clientRepository->findClientsByCnpjs(array_column(array_column($rows, 'attributes'), 'cnpj'));

        return DB::transaction(function () use ($rows, $current) {
            $created = 0;

            foreach ($rows as $row) {
                $attributes = self::withConsentStamp($row['attributes'], $current->get($row['attributes']['cnpj']));
                $created += $this->clientRepository->upsertClientByCnpj($attributes)->wasRecentlyCreated ? 1 : 0;
            }

            return ['created' => $created, 'updated' => count($rows) - $created, 'total' => count($rows)];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  one parsed row
     * @return array<string, mixed>
     */
    private static function withConsentStamp(array $attributes, ?Client $current): array
    {
        if (! array_key_exists('partner_consent', $attributes)) {
            return $attributes;
        }

        $consent = $attributes['partner_consent'];
        $isUnchanged = $current !== null && $consent !== null && $current->partner_consent === $consent;

        if ($isUnchanged) {
            return $attributes;
        }

        return [
            ...$attributes,
            'partner_consent_at' => $consent === null ? null : CarbonImmutable::now(),
            'partner_consent_via' => $consent === null ? null : Client::CONSENT_VIA_CONTRACT,
        ];
    }

    /** The template's content: the header row, after a BOM so a spreadsheet opens it as UTF-8. */
    public function template(): string
    {
        return "\xEF\xBB\xBF".ClientCsvParser::templateHeader()."\n";
    }
}
