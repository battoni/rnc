<?php

namespace App\Modules\Portfolio\Helpers;

use App\Helpers\Brazil\Cnpj;
use App\Modules\Portfolio\Enums\ContractKind;
use App\Modules\Portfolio\Exceptions\ClientImportException;
use Illuminate\Support\Str;

/**
 * Reads the contracts CSV (rnc-3 R18) into contract attributes, line by line. Pure: the firm's
 * clients come in through `$clientIdsOf` (one lookup for every CNPJ of the file).
 *
 * Lines are independent: a good line is a row, a bad one a list of `{line, field, code}` — a bad line
 * never stops the others. Line codes: `client_unknown` (no client of the firm has that CNPJ),
 * `kind_invalid`, `value_invalid`, `year_invalid` (outside 2000–2100), `end_before_start`,
 * `clause_invalid`, `columns`. A file the reader refuses (encoding, header) or with no line or more
 * than `MAX_ROWS` is refused whole, before anything is written.
 *
 * Every stored value is a client id, a `ContractKind`, an integer of cents, a year or a boolean —
 * nothing of a cell is kept as text, so no formula can come back out of the contracts.
 */
class ContractCsvParser
{
    public const COLUMNS = ['cnpj_cliente', 'tipo', 'valor_anual', 'inicio', 'fim', 'clausula_reforma'];

    public const MAX_ROWS = 5000;

    /** The answer lists this many line errors at most; `total_errors` says how many there were. */
    public const MAX_REPORTED_ERRORS = 100;

    public const MIN_YEAR = 2000;

    public const MAX_YEAR = 2100;

    /** R$ 1 bilhão a year: far above any client contract, and its exposure fits an integer. */
    public const MAX_VALUE_CENTS = 100_000_000_000;

    private const CLAUSE_YES = 'sim';

    private const CLAUSE_NO = 'nao';

    private const CENTS_PER_REAL = 100;

    public function __construct(private CsvReader $reader) {}

    /**
     * A row's `clause` is null when its cell was empty.
     *
     * @param  callable(list<string>): array<string, int>  $clientIdsOf  the firm's client ids by 14-digit CNPJ
     * @return array{rows: list<array{line: int, attributes: array{client_id: int, kind: string, annual_value_cents: int, start_year: int, end_year: int}, clause: bool|null}>, errors: list<array{line: int, field: string|null, code: string}>, total: int}
     */
    public function parse(string $content, callable $clientIdsOf): array
    {
        $lines = $this->reader->read($content, self::COLUMNS);

        if ($lines === []) {
            throw ClientImportException::empty();
        }

        if (count($lines) > self::MAX_ROWS) {
            throw ClientImportException::tooMany(self::MAX_ROWS);
        }

        $cnpjs = array_values(array_unique(array_filter(array_map(fn (array $line) => $this->cnpjOf($line['cells']), $lines))));
        $clientIds = $cnpjs === [] ? [] : $clientIdsOf($cnpjs);
        $rows = [];
        $errors = [];

        foreach ($lines as ['line' => $number, 'cells' => $cells]) {
            if ($cells === null) {
                $errors[] = ['line' => $number, 'field' => null, 'code' => 'columns'];

                continue;
            }

            [$row, $lineErrors] = $this->readRow($cells, $clientIds);

            foreach ($lineErrors as [$field, $code]) {
                $errors[] = ['line' => $number, 'field' => $field, 'code' => $code];
            }

            if ($row !== null) {
                $rows[] = ['line' => $number, ...$row];
            }
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($lines)];
    }

    /** The template's content: the BOM and the header row. */
    public static function template(): string
    {
        return CsvReader::template(self::COLUMNS);
    }

    /** @param  array<string, string>|null  $cells */
    private function cnpjOf(?array $cells): ?string
    {
        $cnpj = Cnpj::normalize($cells['cnpj_cliente'] ?? null);

        return $cnpj !== null && strlen($cnpj) === 14 ? $cnpj : null;
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<string, int>  $clientIds
     * @return array{0: array{attributes: array{client_id: int, kind: string, annual_value_cents: int, start_year: int, end_year: int}, clause: bool|null}|null, 1: list<array{0: string, 1: string}>}
     */
    private function readRow(array $cells, array $clientIds): array
    {
        $errors = [];
        $cnpj = $this->cnpjOf($cells);
        $clientId = $cnpj !== null && array_key_exists($cnpj, $clientIds) ? $clientIds[$cnpj] : null;
        $kind = ContractKind::fromInput($cells['tipo']);
        $value = $this->centsOf($cells['valor_anual']);
        $start = $this->yearOf($cells['inicio']);
        $end = $this->yearOf($cells['fim']);
        $clause = $this->clauseOf($cells['clausula_reforma']);

        if ($clientId === null) {
            $errors[] = ['cnpj_cliente', 'client_unknown'];
        }

        if (! $kind) {
            $errors[] = ['tipo', 'kind_invalid'];
        }

        if ($value === null) {
            $errors[] = ['valor_anual', 'value_invalid'];
        }

        if ($start === null) {
            $errors[] = ['inicio', 'year_invalid'];
        }

        if ($end === null) {
            $errors[] = ['fim', 'year_invalid'];
        }

        if ($start !== null && $end !== null && $end < $start) {
            $errors[] = ['fim', 'end_before_start'];
        }

        if ($clause === false) {
            $errors[] = ['clausula_reforma', 'clause_invalid'];
        }

        if ($errors !== [] || ! $kind || $value === null || $start === null || $end === null || $clause === false) {
            return [null, $errors];
        }

        return [[
            'attributes' => ['client_id' => $clientId, 'kind' => $kind->value, 'annual_value_cents' => $value, 'start_year' => $start, 'end_year' => $end],
            'clause' => $clause['value'],
        ], []];
    }

    /**
     * `100000`, `1.200.000`, `1.200.000,00`, `80.000,5` or `95000.00` (an optional `R$`) → integer
     * cents, read as text — never through a float. Zero, negative or over `MAX_VALUE_CENTS` → null.
     */
    private function centsOf(string $value): ?int
    {
        $number = preg_replace('/^R\$\s*/i', '', $value) ?? '';

        if (str_contains($number, ',')) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $number) === 1) {
            $number = str_replace('.', '', $number);
        }

        if (preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $number, $match) !== 1) {
            return null;
        }

        $cents = (int) $match[1] * self::CENTS_PER_REAL + (int) str_pad($match[2] ?? '', 2, '0');

        return $cents >= 1 && $cents <= self::MAX_VALUE_CENTS ? $cents : null;
    }

    private function yearOf(string $value): ?int
    {
        if (preg_match('/^\d{4}$/', $value) !== 1) {
            return null;
        }

        $year = (int) $value;

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $year : null;
    }

    /**
     * `sim` / `não` in any case and accent → `['value' => bool]`; empty → `['value' => null]` (the
     * import decides: "não" on a new row, what is stored on an update); anything else → false.
     *
     * @return array{value: bool|null}|false
     */
    private function clauseOf(string $value): array|false
    {
        $word = Str::lower(Str::ascii($value));

        return match ($word) {
            '' => ['value' => null],
            self::CLAUSE_YES => ['value' => true],
            self::CLAUSE_NO => ['value' => false],
            default => false,
        };
    }
}
