<?php

namespace App\Modules\Diagnostic\Resources;

use App\Modules\Diagnostic\DTOs\CnpjCompanyDTO;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Resources\PublicLeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The CNPJ lookup's answer (rnc-1 R9): `found` with what the registry said, or `unavailable` — an
 * unknown CNPJ and a failed call look the same to the visitor, who goes on with the quiz.
 *
 * @property array{lead: Lead, company: CnpjCompanyDTO|null} $resource
 */
class PublicCnpjLookupResource extends JsonResource
{
    private const FOUND = 'found';

    private const UNAVAILABLE = 'unavailable';

    public function toArray(Request $request): array
    {
        $company = $this->resource['company'];

        return [
            'lookup' => $company ? self::FOUND : self::UNAVAILABLE,
            'company' => ! $company ? null : [
                'name' => $company->name,
                'cnae' => $company->cnae,
                'cnae_description' => $company->cnae_description,
                'porte' => $company->porte,
                'sector' => $company->sector,
                'band' => $company->band,
                'regime' => $company->regime,
            ],
            'lead' => new PublicLeadResource($this->resource['lead']),
        ];
    }
}
