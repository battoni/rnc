<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Tracking\Enums\EventType;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\DemoEnvironment;
use Database\Seeders\Concerns\SeedsEachDemoFirm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Traffic for Reinaldo's funnel (rnc-1 F7, R24), outside production only: 30 days of invented
 * anonymous visitors (page views, some CTA clicks), growing towards today, and the beacons of every
 * seeded lead's own walk — a visit, the CTA, then one beacon per line its timeline holds (F5's
 * `PipelineSeeder`), under the lead's `visitor_uuid` and source. So the funnel narrows step by step,
 * 7 days differ from 30, and its people are the ones on the board.
 *
 * Deterministic (no faker), relative to now, never in the future. Runs inside `CurrentFirm::runAs`
 * and writes `firm_id` itself (state.md Pitfalls); bulk inserts, so it stamps its own timestamps.
 */
class MetricsSeeder extends Seeder
{
    use SeedsEachDemoFirm;

    private const HISTORY_DAYS = 29;

    /** Anonymous visitors a day: a base, a climb towards today (a campaign), and a weekly wobble. */
    private const DAILY_BASE = 14;

    private const WEEKLY_WOBBLE = [0, 3, 5, 2, 6, 1, 4];

    /** The first-touch mix of anonymous traffic, one word per twentieth. */
    private const SOURCE_MIX = [
        'organico', 'organico', 'pago', 'organico', 'direto', 'organico', 'pago', 'organico', 'parceiro', 'direto',
        'organico', 'pago', 'organico', 'direto', 'organico', 'pago', 'organico', 'parceiro', 'direto', 'pago',
    ];

    private const SECTORS = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'];

    /** How many in a hundred anonymous visitors click the CTA, by the page they entered on ('' = the landing). */
    private const CTA_RATE = ['' => 33, 'comercio' => 34, 'industria' => 27, 'servicos' => 41, 'saude' => 46, 'educacao' => 30, 'agro' => 22];

    /** The lead's timeline lines that a beacon of the walk mirrors (`useDiagnostic`). */
    private const LINE_BEACONS = [
        LeadEventType::DiagnosticStarted->value => EventType::DiagnosticStart,
        LeadEventType::ContactCaptured->value => EventType::DiagnosticContact,
        LeadEventType::ResultSeen->value => EventType::DiagnosticResult,
        LeadEventType::MeetingBooked->value => EventType::Booking,
    ];

    private const DIAGNOSTIC_PATH = '/diagnostico';

    private const CHUNK = 500;

    public function run(CurrentFirm $currentFirm): void
    {
        if (! DemoEnvironment::allows()) {
            return;
        }

        $this->forEachDemoFirm($currentFirm, fn (Firm $firm) => $this->seedFirm($firm));
    }

    private function seedFirm(Firm $firm): void
    {
        $now = CarbonImmutable::now();
        $visitors = [];
        $walks = [];

        foreach ($this->anonymousWalks($now) as $walk) {
            $walks[] = $walk;
        }

        foreach ($this->leadWalks() as $walk) {
            $walks[] = $walk;
        }

        foreach ($walks as $walk) {
            $first = $walk['events'][0]['at'];
            $last = $walk['events'][count($walk['events']) - 1]['at'];
            $visitors[] = ['firm_id' => $firm->id, 'uuid' => $walk['uuid'], 'source' => $walk['source'], 'medium' => null, 'first_seen_at' => $first, 'last_seen_at' => $last, 'created_at' => $first, 'updated_at' => $last];
        }

        foreach (array_chunk($visitors, self::CHUNK) as $chunk) {
            Visitor::query()->insert($chunk);
        }

        $ids = Visitor::query()->pluck('id', 'uuid');
        $events = [];

        foreach ($walks as $walk) {
            foreach ($walk['events'] as $event) {
                $events[] = [
                    'firm_id' => $firm->id,
                    'visitor_id' => $ids[$walk['uuid']],
                    'type' => $event['type']->value,
                    'path' => $event['path'],
                    'sector' => $walk['sector'],
                    'source' => $walk['source'],
                    'medium' => null,
                    'lead_id' => $walk['lead_id'],
                    'at' => $event['at'],
                    'created_at' => $event['at'],
                    'updated_at' => $event['at'],
                ];
            }
        }

        foreach (array_chunk($events, self::CHUNK) as $chunk) {
            TrackingEvent::query()->insert($chunk);
        }
    }

    /**
     * The crowd that never started the diagnostic: a visit, a CTA click for about a third (`CTA_RATE`, by page).
     *
     * @return iterable<array{uuid: string, source: string, sector: string|null, lead_id: null, events: list<array{type: EventType, path: string, at: CarbonImmutable}>}>
     */
    private function anonymousWalks(CarbonImmutable $now): iterable
    {
        $visitor = 0;

        for ($day = 0; $day <= self::HISTORY_DAYS; $day++) {
            $size = self::DAILY_BASE + intdiv(self::HISTORY_DAYS - $day, 2) + self::WEEKLY_WOBBLE[$day % count(self::WEEKLY_WOBBLE)];

            for ($position = 0; $position < $size; $position++, $visitor++) {
                $sector = $visitor % 2 === 0 ? null : self::SECTORS[intdiv($visitor, 2) % count(self::SECTORS)];
                $path = $sector === null ? '/' : "/reforma/{$sector}";
                $at = $now->subDays($day)->subMinutes(5 + ($visitor * 53) % 1200);
                $events = [['type' => EventType::Visit, 'path' => $path, 'at' => $at]];

                if (crc32("cta:{$visitor}") % 100 < self::CTA_RATE[$sector ?? '']) {
                    $events[] = ['type' => EventType::Cta, 'path' => $path, 'at' => $at->addMinutes(1)];
                }

                yield ['uuid' => $this->uuid('anon', $visitor), 'source' => self::SOURCE_MIX[$visitor % count(self::SOURCE_MIX)], 'sector' => $sector, 'lead_id' => null, 'events' => $events];
            }
        }
    }

    /**
     * Each seeded lead's own walk: it entered (on its sector's page, or the landing for one in
     * three), clicked the CTA, and sent one beacon per line of its diagnostic and booking.
     *
     * @return iterable<array{uuid: string, source: string, sector: string|null, lead_id: int, events: list<array{type: EventType, path: string, at: CarbonImmutable}>}>
     */
    private function leadWalks(): iterable
    {
        $leads = Lead::query()->whereNotNull('visitor_uuid')->orderBy('id')->get();
        $lines = LeadEvent::query()->whereIn('lead_id', $leads->pluck('id'))->whereIn('type', array_keys(self::LINE_BEACONS))->orderBy('at')->get()->groupBy('lead_id');

        foreach ($leads as $index => $lead) {
            $own = $lines->get($lead->id, collect());
            $start = $own->first();

            if (! $start) {
                continue;
            }

            $sector = $index % 3 === 0 ? null : ($lead->profile['sector'] ?? null);
            $entry = $sector === null ? '/' : "/reforma/{$sector}";
            $startedAt = CarbonImmutable::parse($start->at);
            $events = [
                ['type' => EventType::Visit, 'path' => $entry, 'at' => $startedAt->subMinutes(4)],
                ['type' => EventType::Cta, 'path' => $entry, 'at' => $startedAt->subMinute()],
            ];

            foreach ($own as $line) {
                $events[] = ['type' => self::LINE_BEACONS[$line->type], 'path' => self::DIAGNOSTIC_PATH, 'at' => CarbonImmutable::parse($line->at)];
            }

            yield ['uuid' => (string) $lead->visitor_uuid, 'source' => $lead->source, 'sector' => $sector, 'lead_id' => $lead->id, 'events' => $events];
        }
    }

    /** A UUID v4 fixed by its seed, so the crowd is the same on every `make fresh-seed`. */
    private function uuid(string $kind, int $index): string
    {
        $hex = substr(hash('sha256', "rnc-metrics:{$kind}:{$index}"), 0, 32);

        return Str::lower(sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex(8 + hexdec($hex[16]) % 4),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        ));
    }
}
