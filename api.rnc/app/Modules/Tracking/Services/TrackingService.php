<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Tracking\DTOs\RecordEventDTO;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Repositories\TrackingRepository;
use Illuminate\Support\Carbon;

/**
 * Records one step of an anonymous visitor (rnc-1 R7, plan AM2). The firm is the request's
 * (`ResolveCurrentFirm`), never the body's; `lead_id` is resolved from the lead token and never
 * read from the client.
 */
class TrackingService
{
    public function __construct(
        private CurrentFirm $currentFirm,
        private TrackingRepository $trackingRepository,
    ) {}

    /** Null when the request has no firm to record into. */
    public function record(RecordEventDTO $dto): ?TrackingEvent
    {
        $firmId = $this->currentFirm->id();

        if ($firmId === null) {
            return null;
        }

        $at = Carbon::now();
        $firstTouch = ['firm_id' => $firmId, 'source' => $dto->source, 'medium' => $dto->medium];
        $visitor = $this->trackingRepository->findOrCreateVisitorByUuid($dto->visitor, $firstTouch, $at);

        return $this->trackingRepository->createTrackingEvent([
            'firm_id' => $firmId,
            'visitor_id' => $visitor->id,
            'type' => $dto->type,
            'path' => $dto->path,
            'sector' => $dto->sector,
            'source' => $dto->source,
            'medium' => $dto->medium,
            // From the lead token, never the client (AM2). No token resolves yet: `leads` and its tokens
            // arrive in F3 (AM3), so `lead_token` is accepted and ignored until then.
            'lead_id' => null,
            'at' => $at,
        ]);
    }
}
