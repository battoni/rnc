<?php

namespace App\Modules\Booking\Repositories;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every read is firm-scoped by `BelongsToFirm`. Moments are compared in UTC — the column's zone
 * (AM9) — with Carbon bounds, never raw SQL (AM11).
 */
class AppointmentRepository
{
    private const STORED_FORMAT = 'Y-m-d H:i:s';

    /**
     * The starts already booked in a window, per specialist, as UTC timestamps.
     *
     * @param  list<int>  $specialistIds
     * @return array<int, list<int>>
     */
    public function findBookedStartsBySpecialist(array $specialistIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Appointment::query()
            ->whereIn('specialist_id', $specialistIds)
            ->whereBetween('starts_at', [$this->stored($from), $this->stored($to)])
            ->get(['specialist_id', 'starts_at'])
            ->groupBy('specialist_id')
            ->map(fn (Collection $appointments) => $appointments->map(fn (Appointment $appointment) => $appointment->starts_at->getTimestamp())->values()->all())
            ->all();
    }

    public function isAppointmentSlotTaken(Specialist $specialist, CarbonImmutable $startsAt): bool
    {
        return Appointment::query()->where('specialist_id', $specialist->id)->where('starts_at', $this->stored($startsAt))->exists();
    }

    public function hasAppointmentOfLead(Lead $lead): bool
    {
        return Appointment::query()->where('lead_id', $lead->id)->exists();
    }

    public function findAppointmentOfLead(Lead $lead): ?Appointment
    {
        return Appointment::query()->with('specialist.user')->where('lead_id', $lead->id)->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function createAppointment(array $attributes): Appointment
    {
        return Appointment::query()->create($attributes);
    }

    /** @return Collection<int, Appointment> the meetings starting in the window not yet reminded */
    public function findAppointmentsToRemind(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Appointment::query()
            ->with(['lead', 'specialist.user'])
            ->whereNull('reminded_at')
            ->where('starts_at', '>', $this->stored($from))
            ->where('starts_at', '<=', $this->stored($to))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Marks the reminder as sent, if nobody did it first: the conditional update is the "once" —
     * two runs that overlap cannot both claim the same meeting.
     */
    public function claimAppointmentReminder(Appointment $appointment, CarbonImmutable $now): bool
    {
        return Appointment::query()->whereKey($appointment->id)->whereNull('reminded_at')->update(['reminded_at' => $this->stored($now)]) === 1;
    }

    private function stored(CarbonImmutable $moment): string
    {
        return $moment->utc()->format(self::STORED_FORMAT);
    }
}
