<?php

namespace App\Modules\Fiscal\Resources;

use App\Modules\Fiscal\Services\SupplierCreditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of "Crédito de fornecedores" (R21–R22): the client's profile, the window's purchase
 * documents, purchases a month (cents), the Simples and regime-normal shares by value (%, one
 * decimal), the credit lost per year as a range `[low, high]` in cents, and whether a review was
 * proposed ("Revisão proposta").
 *
 * @phpstan-import-type SupplierCreditRow from SupplierCreditService
 *
 * @property SupplierCreditRow $resource
 */
class SupplierCreditClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $client = $row['client'];

        return [
            'id' => (int) $client->id,
            'name' => $client->name,
            'cnpj' => $client->cnpj,
            'sector' => $client->sector,
            'regime' => $client->regime,
            'documents' => $row['documents'],
            'monthly_purchases_cents' => $row['monthly_purchases_cents'],
            'simples_share' => $row['simples_share'],
            'normal_share' => $row['normal_share'],
            'lost_per_year_cents' => $row['lost_per_year_cents'],
            'proposed' => $row['proposed'],
        ];
    }
}
