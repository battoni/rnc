<?php

namespace App\Modules\Portfolio\Helpers;

use App\Helpers\Brazil\Cnpj;
use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\RevenueBand;
use App\Modules\Diagnostic\Enums\Sector;
use App\Modules\Portfolio\Exceptions\ClientImportException;
use Illuminate\Support\Str;

/**
 * Reads the portfolio CSV (rnc-1 R25) into client attributes, or refuses it whole. Pure: no database.
 *
 * - UTF-8 only; a leading BOM (what a spreadsheet writes) is dropped.
 * - The delimiter is the header's: `;` (a pt-BR spreadsheet's default) or `,`. Quoted fields work.
 * - The header names the columns of `COLUMNS`, in any order; extra columns are ignored.
 * - A value is read the way a person writes it: a sector or a regime by its slug or its label
 *   ("Indústria", "Lucro Real"), a share as `80` or `80%`, a fee as `1850`, `1.850,00` or `1850.00`.
 * - Every problem of every line is collected and reported by its line number in the file (the
 *   header is line 1), so one round of fixes is enough; blank lines are skipped.
 */
class ClientCsvParser
{
    public const COLUMNS = ['nome', 'cnpj', 'setor', 'regime', 'faixa_faturamento', 'percentual_b2b', 'mensalidade'];

    public const MAX_ROWS = 5000;

    /** The smallest fee whose 4–25 % range always holds a multiple of R$ 50 (21 % of 250 > 50). */
    public const MIN_FEE = 250;

    public const MAX_FEE = 99_999_999;

    public const NAME_MAX_LENGTH = 160;

    /** The response lists this many errors at most; `total_errors` says how many there were. */
    public const MAX_REPORTED_ERRORS = 100;

    private const BOM = "\xEF\xBB\xBF";

    private const DELIMITERS = [';', ','];

    /** A client's regime is known: the quiz's "não sei" is not one. */
    private const CLIENT_REGIMES = [Regime::Simples, Regime::Presumido, Regime::Real];

    private const REGIME_WORDS = ['lucro ', ' nacional'];

    private const MAX_SHARE = 100;

    /**
     * @return list<array{line: int, attributes: array{name: string, cnpj: string, sector: string, regime: string, band: string, b2b_share: int, monthly_fee: float}}>
     */
    public function parse(string $content): array
    {
        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, strlen(self::BOM));
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw ClientImportException::encoding();
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $delimiter = $this->delimiterOf($lines[0] ?? '');
        $positions = $this->headerPositions($lines[0] ?? '', $delimiter);

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($lines, 1, null, true) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $lineNumber = $index + 1;
            $fields = str_getcsv($line, $delimiter, '"', '');

            if (count($fields) < count($positions)) {
                $errors[] = ['line' => $lineNumber, 'field' => null, 'code' => 'columns'];

                continue;
            }

            [$attributes, $lineErrors] = $this->readRow($fields, $positions, $seen);

            foreach ($lineErrors as [$field, $code]) {
                $errors[] = ['line' => $lineNumber, 'field' => $field, 'code' => $code];
            }

            if ($attributes['cnpj'] !== '' && ! isset($seen[$attributes['cnpj']])) {
                $seen[$attributes['cnpj']] = $lineNumber;
            }

            $rows[] = ['line' => $lineNumber, 'attributes' => $attributes];
        }

        if ($errors !== []) {
            throw ClientImportException::invalid(array_slice($errors, 0, self::MAX_REPORTED_ERRORS), count($errors));
        }

        if ($rows === []) {
            throw ClientImportException::empty();
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ClientImportException::tooMany(self::MAX_ROWS);
        }

        return $rows;
    }

    /** The header row the template downloads. */
    public static function templateHeader(): string
    {
        return implode(self::DELIMITERS[0], self::COLUMNS);
    }

    private function delimiterOf(string $header): string
    {
        return substr_count($header, ',') > substr_count($header, ';') ? self::DELIMITERS[1] : self::DELIMITERS[0];
    }

    /** @return array<string, int> each column's position in the file */
    private function headerPositions(string $header, string $delimiter): array
    {
        $names = array_map(fn (?string $name) => Str::lower(trim((string) $name)), str_getcsv($header, $delimiter, '"', ''));
        $positions = [];

        foreach (self::COLUMNS as $column) {
            $position = array_search($column, $names, true);

            if ($position !== false) {
                $positions[$column] = (int) $position;
            }
        }

        $missing = array_values(array_diff(self::COLUMNS, array_keys($positions)));

        if ($missing !== []) {
            throw ClientImportException::header($missing);
        }

        return $positions;
    }

    /**
     * @param  list<string|null>  $fields
     * @param  array<string, int>  $positions
     * @param  array<string, int>  $seen  the CNPJs of the lines before, with their line
     * @return array{0: array{name: string, cnpj: string, sector: string, regime: string, band: string, b2b_share: int, monthly_fee: float}, 1: list<array{0: string, 1: string}>}
     */
    private function readRow(array $fields, array $positions, array $seen): array
    {
        $value = fn (string $column) => trim((string) ($fields[$positions[$column]] ?? ''));
        $errors = [];

        $name = preg_replace('/\s+/u', ' ', $value('nome')) ?? '';
        $cnpj = Cnpj::normalize($value('cnpj')) ?? '';
        $sector = Sector::tryFrom($this->slug($value('setor')));
        $regime = $this->regimeOf($value('regime'));
        $band = RevenueBand::tryFrom(Str::lower($value('faixa_faturamento')));
        $share = $this->shareOf($value('percentual_b2b'));
        $fee = $this->feeOf($value('mensalidade'));

        if ($name === '') {
            $errors[] = ['nome', 'required'];
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors[] = ['nome', 'name_too_long'];
        }

        if (! Cnpj::isValid($cnpj)) {
            $errors[] = ['cnpj', 'cnpj_invalid'];
        }

        if (Cnpj::isValid($cnpj) && isset($seen[$cnpj])) {
            $errors[] = ['cnpj', 'cnpj_duplicate'];
        }

        if (! $sector) {
            $errors[] = ['setor', 'sector_invalid'];
        }

        if (! $regime) {
            $errors[] = ['regime', 'regime_invalid'];
        }

        if (! $band) {
            $errors[] = ['faixa_faturamento', 'band_invalid'];
        }

        if ($share === null) {
            $errors[] = ['percentual_b2b', 'b2b_invalid'];
        }

        if ($fee === null) {
            $errors[] = ['mensalidade', 'fee_invalid'];
        }

        $attributes = [
            'name' => $name,
            'cnpj' => Cnpj::isValid($cnpj) ? $cnpj : '',
            'sector' => $sector->value ?? '',
            'regime' => $regime->value ?? '',
            'band' => $band->value ?? '',
            'b2b_share' => $share ?? 0,
            'monthly_fee' => $fee ?? 0.0,
        ];

        return [$attributes, $errors];
    }

    /** "Indústria" → `industria`, "Serviços" → `servicos`. */
    private function slug(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    private function regimeOf(string $value): ?Regime
    {
        $regime = Regime::tryFrom(trim(str_replace(self::REGIME_WORDS, ' ', ' '.$this->slug($value).' ')));

        return in_array($regime, self::CLIENT_REGIMES, true) ? $regime : null;
    }

    /** `80`, `80%` or `80,0` → 80; anything outside 0–100 or not a whole number is refused. */
    private function shareOf(string $value): ?int
    {
        $number = str_replace(',', '.', rtrim($value, '% '));

        if (! is_numeric($number) || (float) $number !== floor((float) $number)) {
            return null;
        }

        $share = (int) $number;

        return $share >= 0 && $share <= self::MAX_SHARE ? $share : null;
    }

    /** `1850`, `1.850`, `1.850,00`, `1850,5` or `1850.50` (an optional `R$`) → 1850.0 / 1850.5. */
    private function feeOf(string $value): ?float
    {
        $number = preg_replace('/^R\$\s*/i', '', $value) ?? '';

        if (str_contains($number, ',')) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $number) === 1) {
            $number = str_replace('.', '', $number);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $number) !== 1) {
            return null;
        }

        $fee = (float) $number;

        return $fee >= self::MIN_FEE && $fee <= self::MAX_FEE ? $fee : null;
    }
}
