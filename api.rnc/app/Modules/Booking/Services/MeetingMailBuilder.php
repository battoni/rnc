<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\DTOs\MeetingMailDTO;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Models\Lead;

/**
 * The values a meeting e-mail prints (plan AM6): read now, inside the request or the command's
 * firm, so the queued Mailable carries values and never queries on its own. A lead started under a
 * sub-brand is written to and signed by that brand (rnc-3 R3), from the firm's own mailbox.
 */
class MeetingMailBuilder
{
    public function __construct(
        private CurrentFirm $currentFirm,
        private RulesEngine $rulesEngine,
    ) {}

    public function build(Appointment $appointment, Lead $lead): MeetingMailDTO
    {
        $firm = $this->currentFirm->get();
        $profile = DiagnosticProfileDTO::from($lead->profile);
        $diagnosis = $profile->isComplete() ? $this->rulesEngine->diagnose($profile) : ['opportunities' => 0, 'risks' => 0];
        $firstName = $lead->contact_name ? strtok(trim($lead->contact_name), ' ') : null;

        return new MeetingMailDTO(
            firstName: $firstName ?: null,
            company: (string) $lead->company,
            specialist: $appointment->specialist->displayName(),
            area: $appointment->specialist->area,
            startsAt: $appointment->starts_at->setTimezone(BookingCalendar::TIMEZONE),
            minutes: BookingCalendar::SLOT_MINUTES,
            opportunities: $diagnosis['opportunities'],
            risks: $diagnosis['risks'],
            reportUrl: config('rnc.frontend_url').'/relatorio/'.$lead->report_token,
            firmName: $lead->brand->name ?? $firm->name ?? (string) config('rnc.brand.name'),
            firmAddress: $firm?->address,
            firmPhone: $firm?->phone,
            firmMailbox: $firm?->mailbox,
        );
    }
}
