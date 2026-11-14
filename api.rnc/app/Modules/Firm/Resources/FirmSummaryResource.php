<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in person's firm as `GET auth/me` answers it (rnc-4 R20): who it is, its main brand's
 * look and its opt-in modules (`FirmModules::enabled` — core is always on and never listed), what
 * the back office's shell needs to boot. No id, no contact data: the public site's `public/firm`
 * carries those.
 *
 * @mixin Firm
 */
class FirmSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'logo_text' => $this->logo_text,
            'logo_url' => $this->logo_path ? Storage::disk(PublicFirmResource::LOGO_DISK)->url($this->logo_path) : null,
            'brand_primary' => $this->brand_primary,
            'brand_accent' => $this->brand_accent,
            'is_platform' => (bool) $this->is_platform,
            'modules' => FirmModules::enabled($this->resource),
        ];
    }
}
