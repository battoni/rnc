<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the public site shows of a firm: its brand and its public contact (rnc-1 R5). An allow-list
 * (`arcus-api-architecture`, "A route without auth answers through a `Public*` resource"): no id,
 * no timestamps, nothing a later column could leak.
 *
 * `phone_href` is the dialable form of the printed phone, so the site never parses a phone number.
 *
 * Under a sub-brand (rnc-3 R2) the look is the brand's — name, logo text, short name, tagline,
 * colours, proof — and the contact stays the firm's: address, phone, mailbox and hours. The main
 * brand answers `brand`, `short_name`, `tagline` and `proof` as null: the site keeps its own copy.
 *
 * @mixin Firm
 */
class PublicFirmResource extends JsonResource
{
    private const BRAZIL_DIALING_PREFIX = 'tel:+55';

    private ?FirmBrand $brand = null;

    /** The sub-brand the firm is shown under; null keeps the main brand. */
    public function underBrand(?FirmBrand $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return [
            'name' => $brand->name ?? $this->name,
            'slug' => $this->slug,
            'brand' => $brand?->slug,
            'logo_text' => $brand->name ?? $this->logo_text,
            'short_name' => $brand?->short_name,
            'tagline' => $brand?->tagline,
            'brand_primary' => $brand->brand_primary ?? $this->brand_primary,
            'brand_accent' => $brand->brand_accent ?? $this->brand_accent,
            'proof' => $brand?->proof,
            'address' => $this->address,
            'phone' => $this->phone,
            'phone_href' => $this->phone ? self::BRAZIL_DIALING_PREFIX.preg_replace('/\D/', '', $this->phone) : null,
            'mailbox' => $this->mailbox,
            'business_hours' => $this->business_hours,
        ];
    }
}
