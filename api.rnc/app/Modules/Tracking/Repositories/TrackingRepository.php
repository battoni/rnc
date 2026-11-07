<?php

namespace App\Modules\Tracking\Repositories;

use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class TrackingRepository
{
    /**
     * The visitor of this UUID in the current firm, created on its first event with the source it
     * arrived from. Two first events racing each other meet the unique (firm, uuid) index: the
     * loser reads the winner's row back (`arcus-api-architecture`, "A concurrent create loses").
     *
     * @param  array{source: string, medium: string|null}  $firstTouch
     */
    public function findOrCreateVisitorByUuid(string $uuid, array $firstTouch, Carbon $at): Visitor
    {
        $visitor = Visitor::query()->where('uuid', $uuid)->first();

        if ($visitor) {
            $visitor->update(['last_seen_at' => $at]);

            return $visitor;
        }

        try {
            return Visitor::query()->create([...$firstTouch, 'uuid' => $uuid, 'first_seen_at' => $at, 'last_seen_at' => $at]);
        } catch (QueryException $exception) {
            return Visitor::query()->where('uuid', $uuid)->first() ?? throw $exception;
        }
    }

    /** @param  array<string, mixed>  $attributes */
    public function createTrackingEvent(array $attributes): TrackingEvent
    {
        return TrackingEvent::query()->create($attributes);
    }
}
