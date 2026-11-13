<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Lead\Services\LeadService;

/**
 * The "Relatório de Impacto" (rnc-1 R17, plan AM4), by its own token: the company, the result
 * (titles and ranges — the details stay for the meeting), the 2026–2033 simulation and the meeting.
 * Null for an unknown or expired token, and for a lead without a complete profile.
 */
class ReportService
{
    public function __construct(
        private LeadService $leadService,
        private AppointmentRepository $appointmentRepository,
        private RulesEngine $rulesEngine,
    ) {}

    /**
     * @return array{company: string|null, profile: array<string, mixed>, result: array<string, mixed>, simulation: array<string, mixed>, meeting: Appointment|null}|null
     */
    public function report(string $reportToken): ?array
    {
        $lead = $this->leadService->findLeadByReportToken($reportToken);

        if (! $lead) {
            return null;
        }

        $profile = DiagnosticProfileDTO::from($lead->profile);

        if (! $profile->isComplete()) {
            return null;
        }

        return [
            'company' => $lead->company,
            'profile' => $lead->profile,
            'result' => ['company' => $lead->company, ...$this->rulesEngine->diagnose($profile)],
            'simulation' => ['years' => $this->rulesEngine->taxMix($profile), 'split_payment' => $this->rulesEngine->splitPaymentRange($profile)],
            'meeting' => $this->appointmentRepository->findAppointmentOfLead($lead),
        ];
    }
}
