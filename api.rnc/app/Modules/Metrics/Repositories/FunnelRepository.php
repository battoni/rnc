<?php

namespace App\Modules\Metrics\Repositories;

use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Tracking\Enums\EventType;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Carbon\CarbonImmutable;

/**
 * The funnel's reads (R24) over Tracking's `visitors`/`events` and Lead's `leads`/`lead_events` —
 * read, never written (Metrics/RULES.md). Every query is the current firm's (`FirmScope` on the
 * models; a timeline line through its lead). Portable SQL only (AM11): the window is two Carbon
 * bounds in `whereBetween`, distinctness is `DISTINCT` over plain columns, the JSON `to` goes
 * through the query builder's `data->to` (SQLite's and MySQL's `json_extract` alike).
 */
class FunnelRepository
{
    /**
     * Every page view in the window, oldest first: the first one of a visitor is the page it
     * entered on.
     *
     * @return list<array{visitor_id: int, path: string}>
     */
    public function findVisitsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return TrackingEvent::query()
            ->where('type', EventType::Visit->value)
            ->whereBetween('at', [$from, $to])
            ->orderBy('at')
            ->orderBy('id')
            ->get(['visitor_id', 'path'])
            ->map(fn (TrackingEvent $event) => ['visitor_id' => (int) $event->visitor_id, 'path' => $event->path])
            ->all();
    }

    /**
     * Which visitors sent each beacon in the window, once per (visitor, type).
     *
     * @param  list<string>  $types
     * @return list<array{visitor_id: int, type: string}>
     */
    public function findBeaconVisitorsBetween(array $types, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return TrackingEvent::query()
            ->whereIn('type', $types)
            ->whereBetween('at', [$from, $to])
            ->distinct()
            ->get(['visitor_id', 'type'])
            ->map(fn (TrackingEvent $event) => ['visitor_id' => (int) $event->visitor_id, 'type' => $event->type])
            ->all();
    }

    /**
     * Each visitor seen since `$from` with the source of its first visit (W15: first touch).
     *
     * @return array<int, string> visitor id → source
     */
    public function findVisitorSourcesSince(CarbonImmutable $from): array
    {
        return Visitor::query()->where('last_seen_at', '>=', $from)->pluck('source', 'id')->all();
    }

    /**
     * Which lead belongs to which visitor: the lead a beacon carried (resolved from its token, AM2),
     * and the lead the diagnostic started with the visitor's UUID (F3, `leads.visitor_uuid`).
     *
     * @return list<array{lead_id: int, visitor_id: int}>
     */
    public function findLeadVisitorsSince(CarbonImmutable $from): array
    {
        $byBeacon = TrackingEvent::query()
            ->whereNotNull('lead_id')
            ->where('at', '>=', $from)
            ->distinct()
            ->get(['lead_id', 'visitor_id'])
            ->map(fn (TrackingEvent $event) => ['lead_id' => (int) $event->lead_id, 'visitor_id' => (int) $event->visitor_id]);

        $byUuid = Lead::query()
            ->join('visitors', function ($join) {
                $join->on('visitors.uuid', '=', 'leads.visitor_uuid')->on('visitors.firm_id', '=', 'leads.firm_id');
            })
            ->where('visitors.last_seen_at', '>=', $from)
            ->get(['leads.id as lead_id', 'visitors.id as visitor_id'])
            ->map(fn (Lead $lead) => ['lead_id' => (int) $lead->getAttribute('lead_id'), 'visitor_id' => (int) $lead->getAttribute('visitor_id')]);

        return $byBeacon->concat($byUuid)->all();
    }

    /**
     * Which leads lived each timeline line in the window, once per (lead, type) — a stage change only
     * when it was to `cliente`.
     *
     * @param  list<string>  $types
     * @return list<array{lead_id: int, type: string}>
     */
    public function findLeadLinesBetween(array $types, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return LeadEvent::query()
            ->whereHas('lead')
            ->whereIn('type', $types)
            ->whereBetween('at', [$from, $to])
            ->where(function ($query) {
                $query->where('type', '!=', LeadEventType::StageChanged->value)
                    ->orWhere('data->to', LeadStage::Cliente->value);
            })
            ->distinct()
            ->get(['lead_id', 'type'])
            ->map(fn (LeadEvent $line) => ['lead_id' => (int) $line->lead_id, 'type' => $line->type])
            ->all();
    }
}
