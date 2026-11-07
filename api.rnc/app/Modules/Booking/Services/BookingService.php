<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\DTOs\BookMeetingDTO;
use App\Modules\Booking\Enums\SpecialistFocus;
use App\Modules\Booking\Exceptions\BookingException;
use App\Modules\Booking\Mail\BookingConfirmed;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Booking\Repositories\SpecialistRepository;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Notification\Notifications\MeetingBookedNotification;
use App\Modules\Notification\Services\TeamNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The visitor's booking (rnc-1 R15–R17), by lead token: the specialists' free slots, and the slot
 * taken — in one transaction with the lead's move to `reuniao_marcada` (AM13) — then the confirmation
 * e-mail and the team's bell. Every write to the lead goes through `LeadService`.
 */
class BookingService
{
    /** The e-mails are pt-BR whatever the API's default locale is (AM15). */
    private const MAIL_LOCALE = 'pt_BR';

    public function __construct(
        private LeadService $leadService,
        private SpecialistRepository $specialistRepository,
        private AppointmentRepository $appointmentRepository,
        private BookingCalendar $calendar,
        private MeetingMailBuilder $meetingMailBuilder,
        private TeamNotifier $teamNotifier,
    ) {}

    /**
     * The next 10 working days (today counted, 2 hours of notice) of every specialist, booked slots left out, and the one indicated for
     * the profile (the demo's `suggestedSpecialist()`). Null for an unknown token.
     *
     * @return array{suggested_specialist_id: int|null, specialists: list<array{specialist: Specialist, suggested: bool, days: list<array{date: string, weekday: int, slots: list<CarbonImmutable>}>}>}|null
     */
    public function availability(string $token): ?array
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        $specialists = $this->specialistRepository->findAllSpecialistsWithHours();
        $focus = SpecialistFocus::forProfile($lead->profile);
        $suggested = $specialists->firstWhere('focus', $focus->value) ?? $specialists->first();
        $now = CarbonImmutable::now();
        $days = $this->calendar->workingDays($now);
        $booked = $days === [] ? [] : $this->appointmentRepository->findBookedStartsBySpecialist(
            $specialists->modelKeys(),
            $days[0],
            end($days)->endOfDay(),
        );

        return [
            'suggested_specialist_id' => $suggested?->id,
            'specialists' => $specialists->map(fn (Specialist $specialist) => [
                'specialist' => $specialist,
                'suggested' => $specialist->id === $suggested?->id,
                'days' => $this->calendar->slotsOf($specialist, $now, $days, $booked[$specialist->id] ?? []),
            ])->values()->all(),
        ];
    }

    /**
     * Books the slot (R16). The unique (specialist, start) index is what makes it safe: a second
     * visitor who reaches the insert first wins, and the loser's constraint error becomes the
     * polite "esse horário acabou de ser reservado" (409).
     *
     * @return array{lead: Lead, appointment: Appointment}|null
     */
    public function book(string $token, BookMeetingDTO $dto): ?array
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        if (! LeadStage::from($lead->stage)->canBeBooked() || $this->appointmentRepository->hasAppointmentOfLead($lead)) {
            throw BookingException::alreadyBooked();
        }

        $email = $dto->email ?? $lead->email;

        if ($email === null) {
            throw BookingException::emailRequired();
        }

        $specialist = $this->specialistRepository->findSpecialistWithHoursById($dto->specialist_id);
        $startsAt = CarbonImmutable::parse($dto->starts_at)->utc();

        if (! $specialist || ! $this->calendar->offers($specialist, CarbonImmutable::now(), $startsAt)) {
            throw BookingException::slotUnavailable();
        }

        if ($this->appointmentRepository->isAppointmentSlotTaken($specialist, $startsAt)) {
            throw BookingException::slotTaken();
        }

        $appointment = $this->reserve($lead, $specialist, $startsAt, $email);

        $this->announce($lead, $appointment, $email);

        return ['lead' => $lead, 'appointment' => $appointment];
    }

    private function reserve(Lead $lead, Specialist $specialist, CarbonImmutable $startsAt, string $email): Appointment
    {
        try {
            return DB::transaction(function () use ($lead, $specialist, $startsAt, $email) {
                $appointment = $this->appointmentRepository->createAppointment([
                    'lead_id' => $lead->id,
                    'specialist_id' => $specialist->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes(BookingCalendar::SLOT_MINUTES),
                ]);

                $this->leadService->recordBooking($lead, $email, [
                    'specialist' => $specialist->displayName(),
                    'starts_at' => BookingCalendar::localIso($startsAt),
                ]);

                return $appointment->setRelation('specialist', $specialist);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->appointmentRepository->hasAppointmentOfLead($lead) ? BookingException::alreadyBooked() : BookingException::slotTaken();
        }
    }

    /** The visitor's confirmation (queued) and the team's bell (R17). */
    private function announce(Lead $lead, Appointment $appointment, string $email): void
    {
        Mail::to($email)->queue((new BookingConfirmed($this->meetingMailBuilder->build($appointment, $lead)))->locale(self::MAIL_LOCALE));

        $this->teamNotifier->notifyTeam(new MeetingBookedNotification(
            $lead->id,
            (string) $lead->company,
            $appointment->specialist->displayName(),
            BookingCalendar::localIso($appointment->starts_at),
        ));
    }
}
