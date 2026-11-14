<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\Helpers\ContractExposure;
use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the contract alert (rnc-3 R19): the client, the contract and its exposure in the
 * 2027–2033 transition, the client's partner consent (the button's gate, rnc-3 R20) and the partners
 * the contract was sent to ("Com <parceiro>"). Money in integer cents; the SPA writes the sentence,
 * the chips and the consent reason. Built with `$referredTo` null — the firm without `partners`
 * (rnc-4 R19) — it answers no referral field: no consent, no `referred_to`.
 *
 * @mixin ClientContract
 */
class ContractAlertResource extends JsonResource
{
    /** @param  list<array{id: int, name: string}>|null  $referredTo  null: no referral field */
    public function __construct(ClientContract $contract, private ?array $referredTo = [])
    {
        parent::__construct($contract);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'client' => [
                'id' => (int) $this->client->id,
                'name' => $this->client->name,
                ...($this->referredTo === null ? [] : [
                    'partner_consent' => $this->client->partner_consent,
                    'partner_consent_via' => $this->client->partner_consent_via,
                ]),
            ],
            'kind' => $this->kind,
            'annual_value_cents' => (int) $this->annual_value_cents,
            'start_year' => (int) $this->start_year,
            'end_year' => (int) $this->end_year,
            'years_exposed' => ContractExposure::years($this->start_year, $this->end_year),
            'exposure_cents' => ContractExposure::cents($this->annual_value_cents, $this->start_year, $this->end_year),
            ...($this->referredTo === null ? [] : ['referred_to' => $this->referredTo]),
        ];
    }
}
