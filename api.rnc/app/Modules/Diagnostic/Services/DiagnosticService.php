<?php

namespace App\Modules\Diagnostic\Services;

use App\Modules\Diagnostic\Contracts\CnpjLookup;
use App\Modules\Diagnostic\DTOs\CnpjCompanyDTO;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\DTOs\StartDiagnosticDTO;
use App\Modules\Diagnostic\DTOs\UpdateDiagnosticDTO;
use App\Modules\Diagnostic\Exceptions\DiagnosticException;
use App\Modules\Firm\Services\CurrentBrand;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Services\LeadService;

/**
 * The public diagnostic (rnc-1 R9–R14): the steps a visitor takes, by lead token. Every write to the
 * lead goes through `LeadService` (the Lead module owns its tables); this module owns the CNPJ
 * lookup and the rules engine. Every method answers null for a token it does not know — the
 * controller's 404 (AM3).
 */
class DiagnosticService
{
    public function __construct(
        private LeadService $leadService,
        private CnpjLookup $cnpjLookup,
        private RulesEngine $rulesEngine,
        private CurrentBrand $currentBrand,
    ) {}

    public function start(StartDiagnosticDTO $dto): ?Lead
    {
        return $this->leadService->startLead([
            'path' => $dto->path,
            'visitor_uuid' => $dto->visitor,
            'source' => $dto->source,
            'medium' => $dto->medium,
            'sector' => $dto->sector,
            // The sub-brand of this visit (rnc-3 R4), stored once at the start.
            'brand_id' => $this->currentBrand->id(),
            'channel' => LeadChannel::from($dto->channel),
        ]);
    }

    /**
     * The CNPJ path (R9). What the registry knows fills the profile — sector always, band and
     * regime when public — and an unknown CNPJ or a failed call is `company: null`: the screen
     * falls back to the quiz (D3).
     *
     * @return array{lead: Lead, company: CnpjCompanyDTO|null}|null
     */
    public function lookupCnpj(string $token, string $cnpj): ?array
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        $company = $this->cnpjLookup->lookup($cnpj);
        $answers = ! $company ? [] : array_filter(
            ['sector' => $company->sector, 'band' => $company->band, 'regime' => $company->regime],
            fn (?string $answer) => $answer !== null,
        );

        return ['lead' => $this->leadService->recordCnpj($lead, $cnpj, $company?->name, $answers), 'company' => $company];
    }

    public function update(string $token, UpdateDiagnosticDTO $dto): ?Lead
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        if ($dto->contact) {
            $this->leadService->recordContact($lead, $dto->contact);
        }

        return $this->leadService->recordProgress($lead, $dto->profile ?? [], $dto->step);
    }

    /**
     * The result (R13): the count, the titles and the ranges. The lead parcial becomes a lead.
     *
     * @return array{company: string|null, opportunities: int, risks: int, items: list<array{id: string, kind: string, title: string, rule: string, detail: string, min: float, max: float}>}|null
     */
    public function result(string $token): ?array
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        $diagnosis = $this->rulesEngine->diagnose($this->completeProfileOf($lead));

        $this->leadService->recordResultSeen($lead, $diagnosis['opportunities'], $diagnosis['risks']);

        return ['company' => $lead->company, ...$diagnosis];
    }

    /**
     * The impact simulator (R14): the 2026–2033 mix and the split payment's monthly cash effect.
     *
     * @return array{years: list<array{year: int, parts: list<array{tax: string, label: string, rate: float}>, total: float}>, split_payment: array{0: float, 1: float}}|null
     */
    public function simulation(string $token): ?array
    {
        $lead = $this->leadService->findLeadByToken($token);

        if (! $lead) {
            return null;
        }

        $profile = $this->completeProfileOf($lead);

        return ['years' => $this->rulesEngine->taxMix($profile), 'split_payment' => $this->rulesEngine->splitPaymentRange($profile)];
    }

    private function completeProfileOf(Lead $lead): DiagnosticProfileDTO
    {
        $profile = DiagnosticProfileDTO::from($lead->profile);

        if (! $profile->isComplete()) {
            throw DiagnosticException::profileIncomplete();
        }

        return $profile;
    }
}
