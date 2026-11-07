<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Lead\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The booking's answer, to the visitor who booked: the meeting and the report's token, so the
 * confirmation screen links the "Relatório de Impacto" too (AM5). Never the e-mail it went to.
 *
 * @property array{lead: Lead, appointment: Appointment} $resource
 */
class PublicBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'report_token' => $this->resource['lead']->report_token,
            'meeting' => (new PublicMeetingResource($this->resource['appointment']))->toArray($request),
        ];
    }
}
