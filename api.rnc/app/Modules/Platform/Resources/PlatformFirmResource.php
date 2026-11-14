<?php

namespace App\Modules\Platform\Resources;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\FirmLogoService;
use App\Modules\Firm\Services\MailBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One firm as the console shows it (rnc-4 R25): its look (with the uploaded logo's `logo_url`, F4c), its opt-in modules, its address
 * (`site_url`, the subdomain link), when it was made and its lead count. Built from
 * `['firm' => Firm, 'lead_count' => int]`. Operator only, so it carries no contact data beyond what
 * the console edits.
 *
 * @property array{firm: Firm, lead_count: int} $resource
 */
class PlatformFirmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $firm = $this->resource['firm'];

        return [
            'id' => $firm->id,
            'slug' => $firm->slug,
            'name' => $firm->name,
            'logo_text' => $firm->logo_text,
            'logo_url' => $firm->logo_path ? Storage::disk(FirmLogoService::DISK)->url($firm->logo_path) : null,
            'short_name' => $firm->short_name,
            'tagline' => $firm->tagline,
            'proof' => $firm->proof,
            'brand_primary' => $firm->brand_primary,
            'brand_accent' => $firm->brand_accent,
            'modules' => FirmModules::enabled($firm),
            'is_platform' => (bool) $firm->is_platform,
            'site_url' => MailBrand::siteUrlOf($firm),
            'created_at' => $firm->created_at?->toIso8601String(),
            'lead_count' => $this->resource['lead_count'],
        ];
    }
}
