<?php

namespace App\Modules\Diagnostic\Services;

use App\Modules\Diagnostic\Contracts\CnpjLookup;
use App\Modules\Diagnostic\DTOs\CnpjCompanyDTO;
use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\RevenueBand;
use App\Modules\Diagnostic\Enums\Sector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * BrasilAPI's free CNPJ endpoint (`/api/cnpj/v1/{cnpj}`, no key), mapped per plan AM10:
 *
 * - porte ME → band b1, EPP → b2, anything else (DEMAIS, absent) → null, the quiz asks;
 * - `opcao_pelo_simples` true, or `opcao_pelo_mei` true → Simples; otherwise null, the quiz asks
 *   (Presumido and Real are not public);
 * - the sector from the first two digits of the main CNAE (`Sector::fromCnae`).
 *
 * 5 s timeout. A found company is cached for 30 days under `cnpj:{14 digits}`; a failure is not
 * cached, so the next visitor asks again.
 */
class BrasilApiCnpjLookup implements CnpjLookup
{
    private const ENDPOINT = 'https://brasilapi.com.br/api/cnpj/v1/';

    private const TIMEOUT_SECONDS = 5;

    private const CACHE_DAYS = 30;

    private const CACHE_PREFIX = 'cnpj:';

    /** BrasilAPI's `codigo_porte`: 1 micro empresa, 3 empresa de pequeno porte, 5 demais. */
    private const PORTE_CODE_ME = 1;

    private const PORTE_CODE_EPP = 3;

    private const PORTE_NAMES_ME = ['ME', 'MICRO EMPRESA', 'MICROEMPRESA'];

    private const PORTE_NAMES_EPP = ['EPP', 'EMPRESA DE PEQUENO PORTE'];

    public function lookup(string $cnpj): ?CnpjCompanyDTO
    {
        $key = self::CACHE_PREFIX.$cnpj;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return CnpjCompanyDTO::from($cached);
        }

        $company = $this->fetch($cnpj);

        if ($company) {
            Cache::put($key, $company->toArray(), now()->addDays(self::CACHE_DAYS));
        }

        return $company;
    }

    private function fetch(string $cnpj): ?CnpjCompanyDTO
    {
        try {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::ENDPOINT.$cnpj);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || empty($body['razao_social'])) {
            return null;
        }

        $cnae = (string) ($body['cnae_fiscal'] ?? '');

        return new CnpjCompanyDTO(
            cnpj: $cnpj,
            name: (string) $body['razao_social'],
            cnae: $cnae,
            cnae_description: $body['cnae_fiscal_descricao'] ?? null,
            porte: $body['porte'] ?? null,
            sector: Sector::fromCnae($cnae)->value,
            band: $this->band($body)?->value,
            regime: $this->isSimples($body) ? Regime::Simples->value : null,
        );
    }

    /** @param  array<string, mixed>  $body */
    private function band(array $body): ?RevenueBand
    {
        $code = (int) ($body['codigo_porte'] ?? 0);
        $name = mb_strtoupper(trim((string) ($body['porte'] ?? '')));

        if ($code === self::PORTE_CODE_ME || in_array($name, self::PORTE_NAMES_ME, true)) {
            return RevenueBand::UpTo360Thousand;
        }

        if ($code === self::PORTE_CODE_EPP || in_array($name, self::PORTE_NAMES_EPP, true)) {
            return RevenueBand::UpTo4Point8Million;
        }

        return null;
    }

    /** @param  array<string, mixed>  $body */
    private function isSimples(array $body): bool
    {
        return ($body['opcao_pelo_simples'] ?? null) === true || ($body['opcao_pelo_mei'] ?? null) === true;
    }
}
