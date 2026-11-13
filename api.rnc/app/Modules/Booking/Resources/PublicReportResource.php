<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Diagnostic\Resources\PublicDiagnosticResultResource;
use App\Modules\Diagnostic\Resources\PublicSimulationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "Relatório de Impacto" (AM4) — an allow-list: the company, the profile answers, the public
 * result (no detail, no cited rule), the simulation and the meeting. Never a contact, a token or
 * the lead's id.
 *
 * @property array{company: string|null, profile: array<string, mixed>, result: array<string, mixed>, simulation: array<string, mixed>, meeting: Appointment|null} $resource
 */
class PublicReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource['profile'];
        $meeting = $this->resource['meeting'];

        return [
            'company' => $this->resource['company'],
            'profile' => [
                'sector' => $profile['sector'] ?? null,
                'regime' => $profile['regime'] ?? null,
                'band' => $profile['band'] ?? null,
                'b2b' => isset($profile['b2b']) ? (int) $profile['b2b'] : null,
            ],
            'result' => (new PublicDiagnosticResultResource($this->resource['result']))->toArray($request),
            'simulation' => (new PublicSimulationResource($this->resource['simulation']))->toArray($request),
            'meeting' => $meeting ? (new PublicMeetingResource($meeting))->toArray($request) : null,
        ];
    }
}
