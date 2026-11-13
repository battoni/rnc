<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Models\FirmBrand;

/**
 * The sub-brand a public request is shown under (rnc-3 R2): null is the firm's main brand.
 *
 * A container binding, scoped like `CurrentFirm`, set by `ResolveCurrentBrand` from the `X-Brand`
 * header on the public routes only. It changes what the visitor reads — never which firm's rows a
 * query sees, which stays `CurrentFirm`'s alone.
 */
class CurrentBrand
{
    private ?FirmBrand $brand = null;

    public function get(): ?FirmBrand
    {
        return $this->brand;
    }

    public function id(): ?int
    {
        return $this->brand?->id;
    }

    public function set(?FirmBrand $brand): void
    {
        $this->brand = $brand;
    }
}
