<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Lead\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A card on the board (R18): who, where it stopped, what the diagnostic found, the meeting when
 * there is one and why it was lost. Staff only — it carries the contact's name.
 *
 * @property array{lead: Lead, opportunities: int, risks: int, meeting: Appointment|null} $resource
 */
class LeadCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lead = $this->resource['lead'];
        $meeting = $this->resource['meeting'];

        return [
            'id' => $lead->id,
            'company' => $lead->company,
            'contact_name' => $lead->contact_name,
            'stage' => $lead->stage,
            'last_step' => $lead->last_step,
            'sector' => $lead->profile['sector'] ?? null,
            'channel' => $lead->channel,
            'source' => $lead->source,
            'has_contact' => $lead->hasContact(),
            'opportunities' => $this->resource['opportunities'],
            'risks' => $this->resource['risks'],
            'lost_reason' => $lead->lost_reason,
            'meeting' => $meeting ? new LeadMeetingResource($meeting) : null,
            'created_at' => $lead->created_at->toIso8601String(),
            'updated_at' => $lead->updated_at->toIso8601String(),
        ];
    }
}
