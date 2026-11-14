<?php

namespace App\Modules\Platform\Resources;

use App\Modules\Firm\DTOs\ProvisionedFirm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A firm the console just made (rnc-4 R25): the firm as the list shows it, its manager, and the
 * manager's password — in this one answer only.
 *
 * @property ProvisionedFirm $resource
 */
class ProvisionedFirmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'firm' => (new PlatformFirmResource(['firm' => $this->resource->firm, 'lead_count' => 0]))->resolve($request),
            'manager' => ManagerLoginResource::managerOf($this->resource->manager),
            'password' => $this->resource->password,
        ];
    }
}
