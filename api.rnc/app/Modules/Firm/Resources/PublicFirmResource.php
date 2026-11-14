<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * What the public site shows of a firm: its brand and its public contact (rnc-1 R5). An allow-list
 * (`arcus-api-architecture`, "A route without auth answers through a `Public*` resource"): no id,
 * no timestamps, nothing a later column could leak.
 *
 * `phone_href` is the dialable form of the printed phone, so the site never parses a phone number.
 *
 * Under a sub-brand (rnc-3 R2) the look is the brand's — name, logo text, short name, tagline,
 * colours, proof — and the contact stays the firm's: address, phone, mailbox and hours. Under a
 * sub-brand `logo_url` is null: the brand has no logo of its own and the firm's would contradict it.
 *
 * *Retired 14/11/2026 (rnc-4 F2a, R8):* "the main brand answers `short_name`, `tagline` and `proof`
 * as null: the site keeps its own copy". A firm now has its own (`firms` columns); a null `tagline`
 * or `proof` still means "the site's own copy".
 *
 * `is_platform` says the root's firm (D3); `platform.name` is the product's name, one config value
 * (R9, `PLATFORM_NAME`) — no other file names the product.
 *
 * `modules` is the firm's opt-in list (rnc-4 R17, `FirmModules::enabled`, as `auth/me` answers it —
 * core is always on and never listed): the site hides what the firm has off, the chat receptionist
 * above all. The firm's, under a sub-brand too — a brand has no modules of its own.
 *
 * @mixin Firm
 */
class PublicFirmResource extends JsonResource
{
    private const BRAZIL_DIALING_PREFIX = 'tel:+55';

    public const LOGO_DISK = 'public';

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
            'short_name' => $brand ? $brand->short_name : $this->short_name,
            'tagline' => $brand ? $brand->tagline : $this->tagline,
            'brand_primary' => $brand->brand_primary ?? $this->brand_primary,
            'brand_accent' => $brand->brand_accent ?? $this->brand_accent,
            'proof' => $brand ? $brand->proof : $this->proof,
            'logo_url' => ! $brand && $this->logo_path ? Storage::disk(self::LOGO_DISK)->url($this->logo_path) : null,
            'is_platform' => (bool) $this->is_platform,
            'address' => $this->address,
            'phone' => $this->phone,
            'phone_href' => $this->phone ? self::BRAZIL_DIALING_PREFIX.preg_replace('/\D/', '', $this->phone) : null,
            'mailbox' => $this->mailbox,
            'business_hours' => $this->business_hours,
            'platform' => ['name' => (string) config('platform.name')],
            'modules' => FirmModules::enabled($this->resource),
        ];
    }
}
