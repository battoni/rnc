<?php

namespace App\Modules\Portfolio\Services;

use App\Modules\Portfolio\Helpers\ClientCsvParser;
use App\Modules\Portfolio\Repositories\ClientRepository;
use Illuminate\Support\Facades\DB;

/**
 * The portfolio's CSV import (rnc-1 R25, A13): all or nothing. The parser refuses the file whole
 * before anything is written; the writes run in one transaction, so a failure half-way leaves the
 * portfolio as it was. A CNPJ the firm already has is updated in place (its repricing mark stays:
 * it is the record of what was proposed then).
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

        return DB::transaction(function () use ($rows) {
            $created = 0;

            foreach ($rows as $row) {
                $created += $this->clientRepository->upsertClientByCnpj($row['attributes'])->wasRecentlyCreated ? 1 : 0;
            }

            return ['created' => $created, 'updated' => count($rows) - $created, 'total' => count($rows)];
        });
    }

    /** The template's content: the header row, after a BOM so a spreadsheet opens it as UTF-8. */
    public function template(): string
    {
        return "\xEF\xBB\xBF".ClientCsvParser::templateHeader()."\n";
    }
}
