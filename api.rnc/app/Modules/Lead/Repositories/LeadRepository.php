<?php

namespace App\Modules\Lead\Repositories;

use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Illuminate\Support\Carbon;

/** Every read is firm-scoped by `BelongsToFirm`: another firm's token is simply not found. */
class LeadRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function createLead(array $attributes): Lead
    {
        return Lead::query()->create($attributes);
    }

    /** The lead of a token that has not expired, in the current firm. */
    public function findLeadByLiveToken(string $token, Carbon $now): ?Lead
    {
        return Lead::query()->where('token', $token)->where('token_expires_at', '>', $now)->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateLead(Lead $lead, array $attributes): Lead
    {
        $lead->update($attributes);

        return $lead;
    }

    /** @param  array<string, mixed>  $attributes */
    public function createLeadConsent(Lead $lead, array $attributes): LeadConsent
    {
        return $lead->consents()->create($attributes);
    }

    /** @param  array<string, mixed>|null  $data */
    public function createLeadEvent(Lead $lead, string $type, ?array $data, Carbon $at): LeadEvent
    {
        return $lead->events()->create(['type' => $type, 'data' => $data, 'at' => $at]);
    }

    public function hasLeadEvent(Lead $lead, string $type): bool
    {
        return $lead->events()->where('type', $type)->exists();
    }
}
