<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the public site shows of a firm: its brand and its public contact (rnc-1 R5). An allow-list
 * (`arcus-api-architecture`, "A route without auth answers through a `Public*` resource"): no id,
 * no timestamps, nothing a later column could leak.
 *
 * `phone_href` is the dialable form of the printed phone, so the site never parses a phone number.
 *
 * @mixin Firm
 */
class PublicFirmResource extends JsonResource
{
    private const BRAZIL_DIALING_PREFIX = 'tel:+55';

    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_text' => $this->logo_text,
            'brand_primary' => $this->brand_primary,
            'brand_accent' => $this->brand_accent,
            'address' => $this->address,
            'phone' => $this->phone,
            'phone_href' => $this->phone ? self::BRAZIL_DIALING_PREFIX.preg_replace('/\D/', '', $this->phone) : null,
            'mailbox' => $this->mailbox,
            'business_hours' => $this->business_hours,
        ];
    }
}
