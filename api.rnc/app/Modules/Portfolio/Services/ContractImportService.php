<?php

namespace App\Modules\Portfolio\Services;

use App\Modules\Portfolio\Helpers\ContractCsvParser;
use App\Modules\Portfolio\Models\ClientContract;
use App\Modules\Portfolio\Repositories\ClientRepository;
use App\Modules\Portfolio\Repositories\ContractRepository;
use Illuminate\Support\Facades\DB;

/**
 * The contracts CSV (rnc-3 R18, A16, AM16). Lines are independent: the good ones are written in one
 * transaction, the bad ones reported by line. The same client + kind + start + end updates its row.
 *
 * The clause: "sim"/"não" sets it; an empty cell is "não" on a new row and keeps what is stored on
 * an update. A contract marked through "Cláusula incluída" (`clause_marked_at`) keeps its clause
 * whatever the file says — a re-import never downgrades it (AM16, W77).
 * A "sim" from a file is the file's: it stamps no mark, so a later file may correct it (review A3, W114).
 */
class ContractImportService
{
    public function __construct(
        private ContractCsvParser $parser,
        private ClientRepository $clientRepository,
        private ContractRepository $contractRepository,
    ) {}

    /** @return array{created: int, updated: int, total: int, errors: list<array{line: int, field: string|null, code: string}>, total_errors: int} */
    public function import(string $content): array
    {
        $parsed = $this->parser->parse($content, fn (array $cnpjs) => $this->clientRepository->findClientIdsByCnpjs($cnpjs));

        $created = DB::transaction(function () use ($parsed) {
            $clientIds = array_values(array_unique(array_map(fn (array $row) => $row['attributes']['client_id'], $parsed['rows'])));
            $stored = $this->contractRepository->findContractsByClientIds($clientIds)->keyBy(fn (ClientContract $contract) => self::keyOf($contract->toArray()))->all();
            $created = 0;

            foreach ($parsed['rows'] as $row) {
                $key = self::keyOf($row['attributes']);
                $isNew = ! isset($stored[$key]);
                $stored[$key] = $this->write($stored[$key] ?? null, $row['attributes'], $row['clause']);
                $created += $isNew ? 1 : 0;
            }

            return $created;
        });

        return [
            'created' => $created,
            'updated' => count($parsed['rows']) - $created,
            'total' => $parsed['total'],
            'errors' => array_slice($parsed['errors'], 0, ContractCsvParser::MAX_REPORTED_ERRORS),
            'total_errors' => count($parsed['errors']),
        ];
    }

    public function template(): string
    {
        return ContractCsvParser::template();
    }

    /** @param  array{client_id: int, kind: string, annual_value_cents: int, start_year: int, end_year: int}  $attributes */
    private function write(?ClientContract $contract, array $attributes, ?bool $clause): ClientContract
    {
        if (! $contract) {
            return $this->contractRepository->createContract([...$attributes, 'has_reforma_clause' => $clause ?? false]);
        }

        $keepsClause = $clause === null || $contract->isClauseMarked();

        return $this->contractRepository->updateContract($contract, [
            'annual_value_cents' => $attributes['annual_value_cents'],
            'has_reforma_clause' => $keepsClause ? $contract->has_reforma_clause : $clause,
        ]);
    }

    /** @param  array<string, mixed>  $contract  the row's key: client, kind, start and end */
    private static function keyOf(array $contract): string
    {
        return implode(':', [$contract['client_id'], $contract['kind'], $contract['start_year'], $contract['end_year']]);
    }
}
