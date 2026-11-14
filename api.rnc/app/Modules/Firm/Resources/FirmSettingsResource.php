<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The manager's settings screen (rnc-4 R30): the firm's name and slug to read, and what the manager
 * edits — contact, business hours, credentials line. An allow-list: the look, the modules and the
 * network's money model are not the manager's and are not answered here.
 *
 * @mixin Firm
 */
class FirmSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'phone' => $this->phone,
            'mailbox' => $this->mailbox,
            'business_hours' => $this->business_hours,
            'credentials' => $this->credentials,
        ];
    }
}
