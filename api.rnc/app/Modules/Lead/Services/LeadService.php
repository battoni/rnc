<?php

namespace App\Modules\Lead\Services;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\DTOs\LeadContactDTO;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Enums\LeadStep;
use App\Modules\Lead\Exceptions\LeadException;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Repositories\LeadRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of `leads`, `lead_consents` and `lead_events` (rnc-1 F3). The Diagnostic module
 * asks it for every change, so the stage rules (AM13) and the timeline live in one place.
 */
class LeadService
{
    private const TOKEN_LENGTH = 48;

    private const TOKEN_DAYS = 30;

    private const FORM_CHANNEL = 'formulario';

    /** The answers a profile holds, all unanswered at the start. */
    private const EMPTY_PROFILE = ['sector' => null, 'regime' => null, 'band' => null, 'b2b' => null, 'incentivos' => false, 'exporta' => false];

    public function __construct(
        private CurrentFirm $currentFirm,
        private LeadRepository $leadRepository,
    ) {}

    /**
     * Step 1: a `visitante` with no personal data (AM13), and its token. Null when the request has
     * no firm to create it in.
     *
     * @param  array{path: string, visitor_uuid: string|null, source: string, medium: string|null, sector: string|null}  $start
     */
    public function startLead(array $start): ?Lead
    {
        if ($this->currentFirm->id() === null) {
            return null;
        }

        $now = Carbon::now();

        return DB::transaction(function () use ($start, $now) {
            $lead = $this->leadRepository->createLead([
                'token' => Str::random(self::TOKEN_LENGTH),
                'token_expires_at' => $now->copy()->addDays(self::TOKEN_DAYS),
                'visitor_uuid' => $start['visitor_uuid'],
                'stage' => LeadStage::Visitante->value,
                'last_step' => LeadStep::Empresa->value,
                'path' => $start['path'],
                'profile' => [...self::EMPTY_PROFILE, 'sector' => $start['sector']],
                'channel' => self::FORM_CHANNEL,
                'source' => $start['source'],
                'medium' => $start['medium'],
            ]);

            $this->leadRepository->createLeadEvent($lead, LeadEventType::DiagnosticStarted->value, ['path' => $start['path']], $now);

            return $lead;
        });
    }

    /** An unknown, expired or other firm's token is null — the caller answers 404 (AM3). */
    public function findLeadByToken(string $token): ?Lead
    {
        return $this->leadRepository->findLeadByLiveToken($token, Carbon::now());
    }

    /**
     * The CNPJ the visitor typed and what the registry said about it: the company name and the
     * answers it could fill. A lookup that found nothing still keeps the CNPJ.
     *
     * @param  array<string, mixed>  $profileAnswers
     */
    public function recordCnpj(Lead $lead, string $cnpj, ?string $company, array $profileAnswers): Lead
    {
        $this->assertOpen($lead);

        $attributes = ['cnpj' => $cnpj, 'path' => 'cnpj', 'profile' => [...$lead->profile, ...$profileAnswers]];

        if ($company !== null) {
            $attributes['company'] = $company;
        }

        $this->leadRepository->updateLead($lead, $attributes);
        $this->leadRepository->createLeadEvent($lead, LeadEventType::CnpjLookedUp->value, ['found' => $company !== null], Carbon::now());

        return $lead;
    }

    /**
     * The contact right after step 1 (R11): the lead becomes a `lead_parcial` and the consent
     * answer is stored with the words read, the time and the channel — given or not (AM12).
     */
    public function recordContact(Lead $lead, LeadContactDTO $contact): Lead
    {
        $this->assertOpen($lead);

        $now = Carbon::now();
        $stage = LeadStage::from($lead->stage);

        return DB::transaction(function () use ($lead, $contact, $now, $stage) {
            $this->leadRepository->updateLead($lead, [
                'company' => $contact->company,
                'contact_name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'stage' => $stage->canAdvanceByDiagnosticTo(LeadStage::LeadParcial) ? LeadStage::LeadParcial->value : $stage->value,
            ]);

            $this->leadRepository->createLeadConsent($lead, [
                'text' => ConsentTerms::TEXT,
                'version' => ConsentTerms::VERSION,
                'given' => $contact->consent,
                'via' => ConsentTerms::VIA_FORM,
                'given_at' => $now,
            ]);

            $this->leadRepository->createLeadEvent($lead, LeadEventType::ContactCaptured->value, ['consent' => $contact->consent], $now);

            return $lead;
        });
    }

    /**
     * A later step: the answers sent are merged over the stored ones (an explicit null clears one)
     * and the step the visitor is now on becomes the lead's "parou em".
     *
     * @param  array<string, mixed>  $profileAnswers
     */
    public function recordProgress(Lead $lead, array $profileAnswers, ?string $step): Lead
    {
        $this->assertOpen($lead);

        $attributes = ['profile' => [...$lead->profile, ...$profileAnswers]];
        $isNewStep = $step !== null && $step !== $lead->last_step;

        if ($step !== null) {
            $attributes['last_step'] = $step;
        }

        $this->leadRepository->updateLead($lead, $attributes);

        if ($isNewStep) {
            $this->leadRepository->createLeadEvent($lead, LeadEventType::StepReached->value, ['step' => $step], Carbon::now());
        }

        return $lead;
    }

    /** The result was shown (AM13): a lead parcial becomes a lead, once, with one timeline line. */
    public function recordResultSeen(Lead $lead, int $opportunities, int $risks): Lead
    {
        if ($this->leadRepository->hasLeadEvent($lead, LeadEventType::ResultSeen->value)) {
            return $lead;
        }

        $stage = LeadStage::from($lead->stage);
        $attributes = ['last_step' => LeadStep::Resultado->value];

        if ($stage->canAdvanceByDiagnosticTo(LeadStage::Lead)) {
            $attributes['stage'] = LeadStage::Lead->value;
        }

        $this->leadRepository->updateLead($lead, $attributes);
        $this->leadRepository->createLeadEvent($lead, LeadEventType::ResultSeen->value, ['opportunities' => $opportunities, 'risks' => $risks], Carbon::now());

        return $lead;
    }

    private function assertOpen(Lead $lead): void
    {
        if (LeadStage::from($lead->stage)->locksProfile()) {
            throw LeadException::locked();
        }
    }
}
