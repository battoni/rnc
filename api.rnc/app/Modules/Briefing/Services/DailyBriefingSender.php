<?php

namespace App\Modules\Briefing\Services;

use App\Modules\Booking\Helpers\Holidays;
use App\Modules\Booking\Services\BookingCalendar;
use App\Modules\Briefing\DTOs\BriefingMailDTO;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Briefing\Repositories\BriefingRepository;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * The 07:00 e-mail (rnc-3 R27, D13): on a business day in São Paulo — not a weekend, not one of
 * Booking's national holidays — every firm in turn builds its briefing once, under itself, and
 * queues it to each of its active ceos. Nothing on a day off: the screen still answers, the e-mail
 * waits for the next business day.
 */
class DailyBriefingSender
{
    private const MAIL_LOCALE = 'pt_BR';

    public function __construct(
        private BriefingService $briefingService,
        private BriefingRepository $briefingRepository,
        private CurrentFirm $currentFirm,
    ) {}

    /** @return int the e-mails queued; 0 on a day off */
    public function send(): int
    {
        $today = CarbonImmutable::now(BookingCalendar::TIMEZONE);

        if ($today->isWeekend() || Holidays::isHoliday($today)) {
            return 0;
        }

        $queued = 0;

        $this->currentFirm->forEachFirm(function (Firm $firm) use (&$queued) {
            $ceos = $this->briefingRepository->findActiveCeosOfFirm($firm->id);

            if ($ceos->isEmpty()) {
                return;
            }

            $briefing = $this->briefingService->build();

            foreach ($ceos as $ceo) {
                $mail = new DailyBriefingMail(new BriefingMailDTO(
                    firstName: $ceo->name,
                    firmName: $firm->name,
                    frontendUrl: (string) config('rnc.frontend_url'),
                    briefing: $briefing,
                ));

                Mail::to($ceo->email)->queue($mail->locale(self::MAIL_LOCALE));
                $queued++;
            }
        });

        return $queued;
    }
}
