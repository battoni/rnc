<?php

namespace App\Modules\Portfolio\Helpers;

use App\Modules\Portfolio\Exceptions\ClientImportException;
use Illuminate\Support\Str;

/**
 * Reads a CSV the way the portfolio's imports accept it (rnc-1 R25, rnc-3 R18), before any column is
 * interpreted. Pure: no database, nothing evaluated — a cell is only ever text handed to a parser
 * that turns it into a number, a year, a slug or a CNPJ.
 *
 * - UTF-8 only (`import_encoding`); a leading BOM (what a spreadsheet writes) is dropped.
 * - The delimiter is the header's: `;` (a pt-BR spreadsheet's default) or `,`. Quoted fields work.
 * - The header names `$columns` in any order, any case; extra columns are ignored; a missing one is
 *   `import_header` with `missing`.
 * - Blank lines are skipped; each line keeps its number in the file (the header is line 1).
 *
 * Same rules as `ClientCsvParser`'s own reading, which keeps its copy until rnc-2's consent column
 * lands there (rnc-3 W76) — then it moves onto this class.
 */
final class CsvReader
{
    private const BOM = "\xEF\xBB\xBF";

    private const DELIMITERS = [';', ','];

    /**
     * @param  list<string>  $columns
     * @return list<array{line: int, cells: array<string, string>|null}> `cells` by column, trimmed; null when the line has fewer fields than the header names
     */
    public function read(string $content, array $columns): array
    {
        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, strlen(self::BOM));
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw ClientImportException::encoding();
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $delimiter = $this->delimiterOf($lines[0] ?? '');
        $positions = $this->headerPositions($lines[0] ?? '', $delimiter, $columns);
        $rows = [];

        foreach (array_slice($lines, 1, null, true) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = str_getcsv($line, $delimiter, '"', '');
            $cells = null;

            if (count($fields) > max($positions)) {
                $cells = array_map(fn (int $position) => trim((string) $fields[$position]), $positions);
            }

            $rows[] = ['line' => $index + 1, 'cells' => $cells];
        }

        return $rows;
    }

    /** The header row a template downloads, after a BOM so a spreadsheet opens it as UTF-8. */
    public static function template(array $columns): string
    {
        return self::BOM.implode(self::DELIMITERS[0], $columns)."\n";
    }

    private function delimiterOf(string $header): string
    {
        return substr_count($header, ',') > substr_count($header, ';') ? self::DELIMITERS[1] : self::DELIMITERS[0];
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, int> each column's position in the file
     */
    private function headerPositions(string $header, string $delimiter, array $columns): array
    {
        $names = array_map(fn (?string $name) => Str::lower(trim((string) $name)), str_getcsv($header, $delimiter, '"', ''));
        $positions = [];

        foreach ($columns as $column) {
            $position = array_search($column, $names, true);

            if ($position !== false) {
                $positions[$column] = (int) $position;
            }
        }

        $missing = array_values(array_diff($columns, array_keys($positions)));

        if ($missing !== []) {
            throw ClientImportException::header($missing);
        }

        return $positions;
    }
}
