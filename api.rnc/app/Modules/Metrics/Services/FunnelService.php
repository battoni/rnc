<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Content\Models\SectorPage;
use App\Modules\Content\Services\SectorPageService;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Metrics\Enums\FunnelStep;
use App\Modules\Metrics\Repositories\FunnelRepository;
use App\Modules\Tracking\Enums\TrafficSource;
use Carbon\CarbonImmutable;

/**
 * The funnel of the last 7 or 30 days (rnc-1 R24, A4) — a cohort: a visitor counts at a step only
 * when it reached every step before it inside the window, so a step is never above the one before
 * (the demo clamped totals; this follows the people). A visitor is the unit to the end: its lead's
 * timeline lines stand in for a lost beacon, and `cliente` is its lead moved there by the team.
 *
 * The visitor's source is its first touch (W15); its sector page is the page of its first visit in
 * the window when that is `/reforma/<slug>`; its channel is the one its lead started in (rnc-2 R14).
 */
class FunnelService
{
    private const SECTOR_PATH = '#^/reforma/([a-z0-9-]+)/?$#';

    /** The steps the sector table shows (the demo's columns). */
    private const SECTOR_STEPS = [FunnelStep::Visitas, FunnelStep::Cta, FunnelStep::Contato, FunnelStep::Agendou];

    /** The steps the source table shows. */
    private const SOURCE_STEPS = [FunnelStep::Visitas, FunnelStep::Cta, FunnelStep::Contato, FunnelStep::Agendou, FunnelStep::Cliente];

    /** The steps the channel table shows: from "início diagnóstico" on, where a channel exists. */
    private const CHANNEL_STEPS = [FunnelStep::Inicio, FunnelStep::Contato, FunnelStep::Resultado, FunnelStep::Agendou, FunnelStep::Cliente];

    public function __construct(
        private FunnelRepository $funnelRepository,
        private SectorPageService $sectorPageService,
    ) {}

    /**
     * @return array{days: int, from: CarbonImmutable, to: CarbonImmutable, steps: list<array{key: string, count: int, drop_off: float|null}>, by_source: list<array<string, int|string>>, by_sector: list<array<string, int|string>>, by_channel: list<array<string, int|string>>}
     */
    public function funnel(int $days): array
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);
        $entries = $this->entries($from, $to);
        $visitorsOfLead = $this->visitorsOfLead($from);
        $depths = $this->depths(array_keys($entries), $this->reached($visitorsOfLead, $from, $to));
        $sources = $this->funnelRepository->findVisitorSourcesSince($from);

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'steps' => $this->steps($depths),
            'by_source' => $this->bySource($depths, $sources),
            'by_sector' => $this->bySector($depths, $entries),
            'by_channel' => $this->byChannel($depths, $this->channelsOf($visitorsOfLead)),
        ];
    }

    /**
     * The visitors with a page view in the window, each with the page it entered on.
     *
     * @return array<int, string> visitor id → entry path
     */
    private function entries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $entries = [];

        foreach ($this->funnelRepository->findVisitsBetween($from, $to) as $visit) {
            $entries[$visit['visitor_id']] ??= $visit['path'];
        }

        return $entries;
    }

    /**
     * How far down the funnel each visitor went: 1 = a visit only, 7 = cliente.
     *
     * @param  list<int>  $visitorIds
     * @param  array<string, array<int, true>>  $reached
     * @return array<int, int> visitor id → number of steps reached in a row
     */
    private function depths(array $visitorIds, array $reached): array
    {
        $depths = [];

        foreach ($visitorIds as $visitorId) {
            $depth = 1;

            foreach (array_slice(FunnelStep::cases(), 1) as $step) {
                if (! isset($reached[$step->value][$visitorId])) {
                    break;
                }

                $depth++;
            }

            $depths[$visitorId] = $depth;
        }

        return $depths;
    }

    /**
     * Which visitors each lead belongs to — by a beacon that carried its token or by its UUID.
     *
     * @return array<int, array<int, true>> lead id → visitor ids
     */
    private function visitorsOfLead(CarbonImmutable $from): array
    {
        $visitorsOfLead = [];

        foreach ($this->funnelRepository->findLeadVisitorsSince($from) as $link) {
            $visitorsOfLead[$link['lead_id']][$link['visitor_id']] = true;
        }

        return $visitorsOfLead;
    }

    /**
     * Every step after the visit, with the visitors who did it — by beacon or by their lead's line.
     *
     * @param  array<int, array<int, true>>  $visitorsOfLead
     * @return array<string, array<int, true>> step → visitor ids
     */
    private function reached(array $visitorsOfLead, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byBeacon = [];
        $byLine = [];

        foreach (FunnelStep::cases() as $step) {
            if ($step->beacon()) {
                $byBeacon[$step->beacon()->value] = $step->value;
            }

            if ($step->leadEvent()) {
                $byLine[$step->leadEvent()->value] = $step->value;
            }
        }

        $reached = [];

        foreach ($this->funnelRepository->findBeaconVisitorsBetween(array_keys($byBeacon), $from, $to) as $row) {
            $reached[$byBeacon[$row['type']]][$row['visitor_id']] = true;
        }

        foreach ($this->funnelRepository->findLeadLinesBetween(array_keys($byLine), $from, $to) as $line) {
            foreach (array_keys($visitorsOfLead[$line['lead_id']] ?? []) as $visitorId) {
                $reached[$byLine[$line['type']]][$visitorId] = true;
            }
        }

        return $reached;
    }

    /**
     * @param  array<int, int>  $depths
     * @return list<array{key: string, count: int, drop_off: float|null}>
     */
    private function steps(array $depths): array
    {
        $counts = $this->counts($depths);
        $steps = [];
        $previous = null;

        foreach (FunnelStep::cases() as $step) {
            $count = $counts[$step->value];
            $steps[] = ['key' => $step->value, 'count' => $count, 'drop_off' => $previous === null ? null : $this->dropOff($previous, $count)];
            $previous = $count;
        }

        return $steps;
    }

    /**
     * The four sources, always all of them, in the site's order.
     *
     * @param  array<int, int>  $depths
     * @param  array<int, string>  $sources
     * @return list<array<string, int|string>>
     */
    private function bySource(array $depths, array $sources): array
    {
        return array_map(function (TrafficSource $source) use ($depths, $sources) {
            $own = array_filter($depths, fn (int $depth, int $visitorId) => ($sources[$visitorId] ?? null) === $source->value, ARRAY_FILTER_USE_BOTH);

            return ['source' => $source->value, ...$this->columns($own, self::SOURCE_STEPS)];
        }, TrafficSource::cases());
    }

    /**
     * Every sector page of the firm, in its own order, with the visitors that entered on it.
     *
     * @param  array<int, int>  $depths
     * @param  array<int, string>  $entries
     * @return list<array<string, int|string>>
     */
    private function bySector(array $depths, array $entries): array
    {
        return $this->sectorPageService->list()->map(function (SectorPage $page) use ($depths, $entries) {
            $own = array_filter($depths, fn (int $depth, int $visitorId) => $this->sectorOf($entries[$visitorId] ?? '') === $page->slug, ARRAY_FILTER_USE_BOTH);

            return ['slug' => $page->slug, 'label' => $page->label, ...$this->columns($own, self::SECTOR_STEPS)];
        })->values()->all();
    }

    /**
     * The visitor's channel is its lead's. A visitor with two leads (a restart after the first got a
     * contact) counts under chat when either is a chat lead — it did talk to the receptionist.
     *
     * @param  array<int, array<int, true>>  $visitorsOfLead
     * @return array<int, string> visitor id → channel
     */
    private function channelsOf(array $visitorsOfLead): array
    {
        $channels = [];

        foreach ($this->funnelRepository->findLeadChannels(array_keys($visitorsOfLead)) as $leadId => $channel) {
            foreach (array_keys($visitorsOfLead[$leadId]) as $visitorId) {
                if (($channels[$visitorId] ?? null) !== LeadChannel::Chat->value) {
                    $channels[$visitorId] = $channel;
                }
            }
        }

        return $channels;
    }

    /**
     * The two public channels, always both, form first (rnc-2 R14).
     *
     * @param  array<int, int>  $depths
     * @param  array<int, string>  $channels
     * @return list<array<string, int|string>>
     */
    private function byChannel(array $depths, array $channels): array
    {
        return array_map(function (LeadChannel $channel) use ($depths, $channels) {
            $own = array_filter($depths, fn (int $depth, int $visitorId) => ($channels[$visitorId] ?? null) === $channel->value, ARRAY_FILTER_USE_BOTH);

            return ['channel' => $channel->value, ...$this->columns($own, self::CHANNEL_STEPS)];
        }, LeadChannel::public());
    }

    /**
     * @param  array<int, int>  $depths
     * @param  list<FunnelStep>  $steps
     * @return array<string, int>
     */
    private function columns(array $depths, array $steps): array
    {
        $counts = $this->counts($depths);

        return array_combine(array_map(fn (FunnelStep $step) => $step->value, $steps), array_map(fn (FunnelStep $step) => $counts[$step->value], $steps));
    }

    /**
     * @param  array<int, int>  $depths
     * @return array<string, int> step → visitors that reached it
     */
    private function counts(array $depths): array
    {
        $counts = [];

        foreach (FunnelStep::cases() as $position => $step) {
            $counts[$step->value] = count(array_filter($depths, fn (int $depth) => $depth > $position));
        }

        return $counts;
    }

    /** The share of the step before that did not come through, in percent with one decimal; 0 when none came. */
    private function dropOff(int $previous, int $count): float
    {
        return $previous === 0 ? 0.0 : round(($previous - $count) / $previous * 100, 1);
    }

    private function sectorOf(string $path): ?string
    {
        return preg_match(self::SECTOR_PATH, $path, $match) === 1 ? $match[1] : null;
    }
}
