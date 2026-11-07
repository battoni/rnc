<?php

namespace App\Modules\Lead\Services;

use App\Models\User;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\DTOs\LeadContactDTO;
use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Enums\LeadStep;
use App\Modules\Lead\Exceptions\LeadException;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Notification\Notifications\NewLeadNotification;
use App\Modules\Notification\Services\TeamNotifier;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Tracking\Enums\TrafficSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of `leads`, `lead_consents` and `lead_events` (rnc-1 F3). The Diagnostic, Booking
 * and Pipeline modules ask it for every change, so the stage rules (AM13) and the timeline live in
 * one place.
 */
class LeadService
{
    private const TOKEN_LENGTH = 48;

    private const TOKEN_DAYS = 30;

    private const REPORT_DAYS = 30;

    /** The answers a profile holds, all unanswered at the start. */
    private const EMPTY_PROFILE = ['sector' => null, 'regime' => null, 'band' => null, 'b2b' => null, 'incentivos' => false, 'exporta' => false];

    public function __construct(
        private CurrentFirm $currentFirm,
        private LeadRepository $leadRepository,
        private TeamNotifier $teamNotifier,
    ) {}

    /**
     * Step 1: a `visitante` with no personal data (AM13), and its token. Null when the request has
     * no firm to create it in. A visitor who already has an open `visitante` (no contact yet, token
     * live) starts that one again — its path and answers reset, its token, first touch and timeline
     * kept — so a restart or a loop on one browser does not pile empty cards on the board. The
     * channel is the new lead's only (rnc-2 R14): a reopened `visitante` keeps the one it began in.
     *
     * @param  array{path: string, visitor_uuid: string|null, source: string, medium: string|null, sector: string|null, channel: LeadChannel}  $start
     */
    public function startLead(array $start): ?Lead
    {
        if ($this->currentFirm->id() === null) {
            return null;
        }

        $now = Carbon::now();
        $open = $start['visitor_uuid'] === null ? null : $this->leadRepository->findOpenVisitanteOfVisitor($start['visitor_uuid'], $now);

        if ($open) {
            return $this->leadRepository->updateLead($open, [
                'path' => $start['path'],
                'last_step' => LeadStep::Empresa->value,
                'profile' => [...self::EMPTY_PROFILE, 'sector' => $start['sector']],
                'company' => null,
                'cnpj' => null,
            ]);
        }

        return DB::transaction(function () use ($start, $now) {
            $lead = $this->leadRepository->createLead([
                'token' => Str::random(self::TOKEN_LENGTH),
                'token_expires_at' => $now->copy()->addDays(self::TOKEN_DAYS),
                'visitor_uuid' => $start['visitor_uuid'],
                'stage' => LeadStage::Visitante->value,
                'last_step' => LeadStep::Empresa->value,
                'path' => $start['path'],
                'profile' => [...self::EMPTY_PROFILE, 'sector' => $start['sector']],
                'channel' => $start['channel']->value,
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

    /** The lead of a live "Relatório de Impacto" link (AM4); null is the report's 404. */
    public function findLeadByReportToken(string $reportToken): ?Lead
    {
        return $this->leadRepository->findLeadByLiveReportToken($reportToken, Carbon::now());
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
     * answer is stored with the words read, the time and the lead's channel — given or not (AM12). The
     * first time a lead becomes a lead parcial the team's bell rings (R17, `NewLead`).
     */
    public function recordContact(Lead $lead, LeadContactDTO $contact): Lead
    {
        $this->assertOpen($lead);

        $now = Carbon::now();
        $stage = LeadStage::from($lead->stage);
        $becomesLeadParcial = $stage->canAdvanceByDiagnosticTo(LeadStage::LeadParcial);

        DB::transaction(function () use ($lead, $contact, $now, $stage, $becomesLeadParcial) {
            $this->leadRepository->updateLead($lead, [
                'company' => $contact->company,
                'contact_name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'stage' => $becomesLeadParcial ? LeadStage::LeadParcial->value : $stage->value,
            ]);

            $this->leadRepository->createLeadConsent($lead, [
                'text' => ConsentTerms::TEXT,
                'version' => ConsentTerms::VERSION,
                'given' => $contact->consent,
                'via' => LeadChannel::from($lead->channel)->consentVia(),
                'given_at' => $now,
            ]);

            $this->leadRepository->createLeadEvent($lead, LeadEventType::ContactCaptured->value, ['consent' => $contact->consent], $now);
        });

        if ($becomesLeadParcial) {
            $this->teamNotifier->notifyTeam(new NewLeadNotification($lead->id, (string) $lead->company, $lead->profile['sector'] ?? null));
        }

        return $lead;
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

    /**
     * The booked meeting (R16, AM4, AM13): the lead becomes `reuniao_marcada` — its answers lock —
     * keeps the e-mail the confirmation goes to, and gets the "Relatório de Impacto" link, valid 30
     * days. The caller holds the transaction that also writes the appointment.
     *
     * @param  array{specialist: string, starts_at: string}  $meeting
     */
    public function recordBooking(Lead $lead, string $email, array $meeting): Lead
    {
        if (! LeadStage::from($lead->stage)->canBeBooked()) {
            throw LeadException::locked();
        }

        $now = Carbon::now();

        $this->leadRepository->updateLead($lead, [
            'email' => $email,
            'stage' => LeadStage::ReuniaoMarcada->value,
            'report_token' => Str::random(self::TOKEN_LENGTH),
            'report_expires_at' => $now->copy()->addDays(self::REPORT_DAYS),
        ]);
        $this->leadRepository->createLeadEvent($lead, LeadEventType::MeetingBooked->value, $meeting, $now);

        return $lead;
    }

    /**
     * The team moves a card (R18, AM13): to any other stage, backwards too; perdido only with the
     * reason, which leaves with the lead when it comes back. The move is a timeline line with who
     * did it and how (`botao`, `arrastar`, `ficha`) — the line F7's funnel counts for "cliente".
     *
     * @return array{lead: Lead, event: LeadEvent}
     */
    public function moveStage(Lead $lead, LeadStage $to, ?string $reason, string $via, User $by): array
    {
        $from = LeadStage::from($lead->stage);
        $reason = trim((string) $reason) ?: null;

        if (! $from->canBeMovedByTeamTo($to)) {
            throw ValidationException::withMessages(['stage' => ['pipeline.sameStage']]);
        }

        if ($to->requiresReason() && $reason === null) {
            throw ValidationException::withMessages(['reason' => ['pipeline.lostReasonRequired']]);
        }

        $keptReason = $to->requiresReason() ? $reason : null;
        $data = ['from' => $from->value, 'to' => $to->value, 'via' => $via, ...($keptReason !== null ? ['reason' => $keptReason] : [])];

        $event = DB::transaction(function () use ($lead, $to, $keptReason, $data, $by) {
            $this->leadRepository->updateLead($lead, ['stage' => $to->value, 'lost_reason' => $keptReason]);

            return $this->leadRepository->createLeadEvent($lead, LeadEventType::StageChanged->value, $data, Carbon::now(), $by->id);
        });

        return ['lead' => $lead, 'event' => $event->setRelation('user', $by)];
    }

    /** A team note (R19): a timeline line with its author. Touches the lead, so the card rises. */
    public function addNote(Lead $lead, string $text, User $by): LeadEvent
    {
        return DB::transaction(function () use ($lead, $text, $by) {
            $lead->touch();

            return $this->leadRepository
                ->createLeadEvent($lead, LeadEventType::NoteAdded->value, ['text' => $text], Carbon::now(), $by->id)
                ->setRelation('user', $by);
        });
    }

    /**
     * A referral to a partner (R22): one timeline line with who sent it and to whom. The stage does
     * not move — Franco stays the lead's owner. The caller (Partner) holds the transaction that also
     * writes the referral; the lead is touched, so its card rises on the board.
     *
     * @param  array{referral_id: int, partner_id: int, partner: string, specialty: string, score: int}  $referral
     */
    public function recordReferral(Lead $lead, array $referral, User $by): LeadEvent
    {
        $lead->touch();

        return $this->leadRepository
            ->createLeadEvent($lead, LeadEventType::ReferralSent->value, $referral, Carbon::now(), $by->id)
            ->setRelation('user', $by);
    }

    /**
     * A partner moved its referral (rnc-2 R9): one timeline line by the partner's login, with where
     * the referral went. The stage does not move — the lead stays Franco's. The caller (Partner)
     * holds the transaction that also moves the referral; the lead is touched, so its card rises.
     *
     * @param  array{referral_id: int, partner_id: int, partner: string, from: string, to: string, meeting_at?: string, deal_value_cents?: int}  $move
     */
    public function recordReferralStatus(Lead $lead, array $move, User $by): LeadEvent
    {
        $lead->touch();

        return $this->leadRepository->createLeadEvent($lead, LeadEventType::ReferralStatusChanged->value, $move, Carbon::now(), $by->id);
    }

    /**
     * The one `carteira` lead of a portfolio client (rnc-2 R27, D13; plan AM10): Franco's own client,
     * so it sits at stage `cliente`, has no contact (the partner's way in is the firm's, R6) and a
     * token born expired — no public route ever opens it. It carries the client's name, CNPJ and the
     * profile the Radar read (the measured B2B share when D9 applies), refreshed on every batch, and
     * the client's consent copied once `via contrato` with the contract's words and moment. The caller
     * (Partner) holds the transaction and has checked the client's consent.
     */
    public function findOrStartPortfolioLead(Client $client, DiagnosticProfileDTO $profile): Lead
    {
        $attributes = ['company' => $client->name, 'cnpj' => $client->cnpj, 'profile' => [...self::EMPTY_PROFILE, ...$profile->toArray()]];
        $lead = $this->leadRepository->findPortfolioLeadOfClient($client->id);

        $now = Carbon::now();

        if ($lead) {
            $this->leadRepository->updateLead($lead, $attributes);

            return $lead->latestConsent ? $lead : $this->copyContractConsent($lead, $client, $now);
        }

        $lead = $this->leadRepository->createOrFindPortfolioLead([
            ...$attributes,
            'token' => Str::random(self::TOKEN_LENGTH),
            'token_expires_at' => $now,
            'stage' => LeadStage::Cliente->value,
            'path' => LeadChannel::Carteira->value,
            'channel' => LeadChannel::Carteira->value,
            'source' => TrafficSource::Direct->value,
            'client_id' => $client->id,
        ]);

        if (! $lead->wasRecentlyCreated) {
            // A racing batch created it after the read above: refresh it as an existing lead.
            $this->leadRepository->updateLead($lead, $attributes);
            $lead->load('latestConsent');

            if ($lead->latestConsent) {
                return $lead;
            }
        }

        return $this->copyContractConsent($lead, $client, $now);
    }

    /** The client's contract consent as the lead's answer — the row every referral of it copies. */
    private function copyContractConsent(Lead $lead, Client $client, Carbon $now): Lead
    {
        $this->leadRepository->createLeadConsent($lead, [
            'text' => ConsentTerms::CONTRACT_TEXT,
            'version' => ConsentTerms::VERSION,
            'given' => true,
            'via' => ConsentTerms::VIA_CONTRACT,
            // `given_at` is a plain `datetime` cast: it stores the wall clock it is handed, so the
            // client's UTC moment goes in the app's zone, as `Carbon::now()` does (Pitfalls).
            'given_at' => $client->partner_consent_at?->setTimezone((string) config('app.timezone')) ?? $now,
        ]);

        return $lead->load('latestConsent');
    }

    private function assertOpen(Lead $lead): void
    {
        if (LeadStage::from($lead->stage)->locksProfile()) {
            throw LeadException::locked();
        }
    }
}
