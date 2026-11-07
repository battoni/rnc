<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lead detail (R19), staff only: the contact, the profile, the FULL diagnostic — each item's
 * detail and cited rule, which the public result never sends — the consent answer as given (text,
 * version, channel, when), the meeting, the timeline and the team's notes.
 *
 * @property array{lead: Lead, diagnosis: array{items: list<array{id: string, kind: string, title: string, rule: string, detail: string, min: float, max: float}>, opportunities: int, risks: int}, meeting: Appointment|null, timeline: Collection<int, LeadEvent>} $resource
 */
class LeadDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lead = $this->resource['lead'];
        $consent = $lead->latestConsent;
        $timeline = $this->resource['timeline'];

        return [
            'id' => $lead->id,
            'company' => $lead->company,
            'cnpj' => $lead->cnpj,
            'contact_name' => $lead->contact_name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'stage' => $lead->stage,
            'last_step' => $lead->last_step,
            'lost_reason' => $lead->lost_reason,
            'path' => $lead->path,
            'channel' => $lead->channel,
            // The sub-brand chip (rnc-3 R4): null for the main brand.
            'brand' => $lead->brand ? ['id' => $lead->brand->id, 'short_name' => $lead->brand->short_name] : null,
            'source' => $lead->source,
            'medium' => $lead->medium,
            'profile' => $lead->profile,
            'diagnosis' => [
                'opportunities' => $this->resource['diagnosis']['opportunities'],
                'risks' => $this->resource['diagnosis']['risks'],
                'items' => array_map(fn (array $item) => [
                    'id' => $item['id'],
                    'kind' => $item['kind'],
                    'title' => $item['title'],
                    'detail' => $item['detail'],
                    'rule' => $item['rule'],
                    'range_min' => (int) round($item['min']),
                    'range_max' => (int) round($item['max']),
                    'range_label' => RulesEngine::formatRange($item['min'], $item['max']),
                ], $this->resource['diagnosis']['items']),
            ],
            'consent' => $consent ? [
                'given' => $consent->given,
                'text' => $consent->text,
                'version' => $consent->version,
                'via' => $consent->via,
                'given_at' => $consent->given_at->toIso8601String(),
            ] : null,
            'meeting' => $this->resource['meeting'] ? new LeadMeetingResource($this->resource['meeting']) : null,
            'timeline' => LeadEventResource::collection($timeline),
            'notes' => LeadEventResource::collection($timeline->where('type', LeadEventType::NoteAdded->value)->values()),
            'created_at' => $lead->created_at->toIso8601String(),
            'updated_at' => $lead->updated_at->toIso8601String(),
        ];
    }
}
