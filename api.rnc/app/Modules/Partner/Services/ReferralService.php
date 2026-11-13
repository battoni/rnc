<?php

namespace App\Modules\Partner\Services;

use App\Models\User;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Partner\DTOs\ReferLeadDTO;
use App\Modules\Partner\DTOs\ReferralMailDTO;
use App\Modules\Partner\Enums\PartnerSpecialty;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Exceptions\ReferralException;
use App\Modules\Partner\Helpers\ContractValueBand;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Repositories\PartnerRepository;
use App\Modules\Partner\Repositories\ReferralRepository;
use App\Modules\Portfolio\Enums\ContractKind;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use App\Modules\Portfolio\Repositories\ContractRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Routing and the trail (rnc-1 R22–R23). Franco recommends a lead to a partner only when the lead
 * said yes (AM12); the consent is copied onto the referral, the lead's timeline gets the line
 * through `LeadService` (its tables are Lead's) and the partner gets `ReferralSent`. The lead's
 * stage does not move: Franco stays its owner.
 */
class ReferralService
{
    private const MAIL_LOCALE = 'pt_BR';

    private const PARTNER_UNAVAILABLE = 'partners.routing.partnerUnavailable';

    public const BLOCKED_CONSENT_MISSING = 'consent_missing';

    public const BLOCKED_CONSENT_REFUSED = 'consent_refused';

    /** plan Defaults: a Radar batch is the 10 largest clients still to send (rnc-2 R27). */
    public const BATCH_SIZE = 10;

    /** rnc-3 R20: who reviews a contract's price clause — advocacia tributária first, then precificação. */
    public const LEGAL_SPECIALTIES = [PartnerSpecialty::AdvocaciaTributaria, PartnerSpecialty::PrecificacaoControladoria];

    /** The client's "desde" year is read in the firm's city (P4). */
    private const LOCAL_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        private CurrentFirm $currentFirm,
        private LeadRepository $leadRepository,
        private LeadService $leadService,
        private PartnerRepository $partnerRepository,
        private ReferralRepository $referralRepository,
        private PartnerMatcher $partnerMatcher,
        private ContractRepository $contractRepository,
    ) {}

    /**
     * Every active partner scored against the lead (R22), with the referral already sent to it, and
     * whether the lead may be referred at all. Null for an unknown or another firm's lead.
     *
     * @return array{can_refer: bool, blocked_reason: string|null, matches: list<array{partner: Partner, score: int, covers: list<array{id: string, title: string}>, referral: Referral|null}>}|null
     */
    public function matches(int $leadId): ?array
    {
        $lead = $this->leadRepository->findLeadById($leadId);

        if (! $lead) {
            return null;
        }

        $sent = $this->referralRepository->findReferralsOfLead($lead);
        $matches = array_map(
            fn (array $match) => [...$match, 'referral' => $sent->get($match['partner']->id)],
            $this->partnerMatcher->match($lead, $this->partnerRepository->findActivePartners()),
        );
        $blockedReason = $this->blockedReason($lead);

        return ['can_refer' => $blockedReason === null, 'blocked_reason' => $blockedReason, 'matches' => $matches];
    }

    /**
     * The referral (R22): 422 `consent_required` without a consent given, 409 `already_referred` for
     * the same pair. Null for an unknown or another firm's lead.
     *
     * @return array{referral: Referral, event: LeadEvent}|null
     */
    public function refer(int $leadId, ReferLeadDTO $referral, User $by): ?array
    {
        $lead = $this->leadRepository->findLeadById($leadId);

        if (! $lead) {
            return null;
        }

        $consent = $lead->latestConsent;

        if (! $consent || ! $consent->given) {
            throw ReferralException::consentRequired();
        }

        $partner = $this->partnerRepository->findPartnerById($referral->partner_id);

        if (! $partner) {
            throw ValidationException::withMessages(['partner_id' => [self::PARTNER_UNAVAILABLE]]);
        }

        if ($this->referralRepository->existsReferralOfLeadToPartner($lead, $partner->id)) {
            throw ReferralException::alreadyReferred();
        }

        $match = $this->partnerMatcher->match($lead, collect([$partner]))[0];

        try {
            $written = DB::transaction(function () use ($lead, $partner, $referral, $by, $consent, $match) {
                $row = $this->referralRepository->createReferral([
                    'lead_id' => $lead->id,
                    'partner_id' => $partner->id,
                    'referred_by' => $by->id,
                    'message' => $referral->message,
                    'score' => $match['score'],
                    'covers' => $match['covers'],
                    'consent_text' => $consent->text,
                    'consent_version' => $consent->version,
                    'consent_given_at' => $consent->given_at,
                    'status' => ReferralStatus::Enviado->value,
                ]);

                $event = $this->leadService->recordReferral($lead, [
                    'referral_id' => $row->id,
                    'partner_id' => $partner->id,
                    'partner' => $partner->name,
                    'specialty' => $partner->specialty,
                    'score' => $match['score'],
                ], $by);

                return ['referral' => $row, 'event' => $event];
            });
        } catch (UniqueConstraintViolationException) {
            throw ReferralException::alreadyReferred();
        }

        Mail::to($partner->email)->queue((new ReferralSent($this->mail($lead, $partner, $written['referral'], $by)))->locale(self::MAIL_LOCALE));

        return ['referral' => $this->referralRepository->loadReferralForTrail($written['referral']), 'event' => $written['event']];
    }

    /**
     * The Radar rows' partner pickers (rnc-2 R27): per rule, the ACTIVE partners whose specialty
     * covers it, by name, each with the size of its next batch and how many of the row's clients it
     * already got. Three queries whatever the rows (partners, portfolio leads, their referrals).
     *
     * @param  array<string, list<Client>>  $rankedByRule  a rule's clients in the Radar's order
     * @return array<string, list<array{id: int, name: string, specialty: string, batch: int, sent: int}>>
     */
    public function portfolioBatchOptions(array $rankedByRule): array
    {
        $partners = $this->partnerRepository->findActivePartners();
        $referred = $this->referredPartnersByClient();
        $options = [];

        foreach ($rankedByRule as $rule => $clients) {
            $options[$rule] = $partners
                ->filter(fn (Partner $partner) => self::covers($partner, DiagnosticRule::from($rule)))
                ->map(fn (Partner $partner) => [
                    'id' => (int) $partner->id,
                    'name' => $partner->name,
                    'specialty' => $partner->specialty,
                    'batch' => count(self::pickBatch($clients, $partner->id, $referred)['batch']),
                    'sent' => count(array_filter($clients, fn (Client $client) => isset($referred[$client->id][$partner->id]))),
                ])
                ->values()
                ->all();
        }

        return $options;
    }

    /**
     * "Encaminhar os N maiores" (rnc-2 R27, D13): the rule's next batch to one partner. The partner
     * must be active and its specialty must cover the rule (422 `partner_not_covering`); the batch is
     * the first `BATCH_SIZE` clients in the Radar's order whose consent is TRUE (null, never answered,
     * counts as no — W98) and who were never sent to this partner. Each goes through the client's one
     * `carteira` lead (Lead's `findOrStartPortfolioLead`), so the referral, its consent copy, the
     * timeline line, the trail and the inbox are the routing panel's own shape; all in ONE transaction,
     * then one `ReferralSent` each, with Franco's contact.
     *
     * @param  list<array{client: Client, profile: DiagnosticProfileDTO}>  $ranked  the rule's clients in the Radar's order
     * @return array{sent: int, skipped_no_consent: int, skipped_already: int}
     */
    public function referBatch(DiagnosticRule $rule, int $partnerId, array $ranked, User $by): array
    {
        $partner = $this->partnerRepository->findActivePartnerById($partnerId);

        if (! $partner) {
            throw ValidationException::withMessages(['partner_id' => [self::PARTNER_UNAVAILABLE]]);
        }

        if (! self::covers($partner, $rule)) {
            throw ReferralException::partnerNotCovering();
        }

        $profiles = [];

        foreach ($ranked as $candidate) {
            $profiles[$candidate['client']->id] = $candidate['profile'];
        }

        $pick = self::pickBatch(array_column($ranked, 'client'), $partner->id, $this->referredPartnersByClient());

        try {
            $referrals = DB::transaction(fn () => array_map(
                fn (Client $client) => $this->referPortfolioClient($client, $profiles[$client->id], $partner, $rule, $by),
                $pick['batch'],
            ));
        } catch (UniqueConstraintViolationException) {
            throw ReferralException::alreadyReferred();
        }

        foreach ($referrals as $referral) {
            Mail::to($partner->email)->queue((new ReferralSent($this->mail($referral->lead, $partner, $referral, $by)))->locale(self::MAIL_LOCALE));
        }

        return ['sent' => count($referrals), 'skipped_no_consent' => $pick['skipped_no_consent'], 'skipped_already' => $pick['skipped_already']];
    }

    /** @return Collection<int, Partner> "Encaminhar ao jurídico"'s picker (rnc-3 R20): advocacia first, then precificação, by name */
    public function legalPartners(): Collection
    {
        return $this->partnerRepository->findActivePartnersBySpecialties(array_column(self::LEGAL_SPECIALTIES, 'value'));
    }

    /**
     * "Encaminhar ao jurídico" (rnc-3 R20, D11): one client contract to one legal partner. The partner
     * must be active in the firm (422 on `partner_id`) and of a legal specialty (422
     * `partner_not_legal`); the client must have said yes in its service contract (`partner_consent`
     * TRUE, else 422 `consent_required` — null, never answered, is no). The referral goes through the
     * client's one portfolio lead (Lead's `findOrStartPortfolioLead`, its `via contrato` consent
     * copied), carries `contract_id` and `subject_key = contrato:<id>` and the demo's message with the
     * value as a range (AM20); one per contract and partner (409 `already_referred`). One timeline
     * line, then `ReferralSent` with Franco's contact. Null for an unknown or another firm's contract.
     */
    public function referContract(int $contractId, int $partnerId, User $by): ?Referral
    {
        $contract = $this->contractRepository->findContractWithClientById($contractId);

        if (! $contract) {
            return null;
        }

        $partner = $this->partnerRepository->findActivePartnerById($partnerId);

        if (! $partner) {
            throw ValidationException::withMessages(['partner_id' => [self::PARTNER_UNAVAILABLE]]);
        }

        if (! in_array($partner->specialtyCase(), self::LEGAL_SPECIALTIES, true)) {
            throw ReferralException::partnerNotLegal();
        }

        $client = $contract->client;

        if ($client->partner_consent !== true) {
            throw ReferralException::consentRequired();
        }

        if ($this->referralRepository->existsReferralOfContractToPartner($contract->id, $partner->id)) {
            throw ReferralException::alreadyReferred();
        }

        try {
            $referral = DB::transaction(function () use ($contract, $client, $partner, $by) {
                $lead = $this->leadService->findOrStartPortfolioLead($client, $client->profile());
                $consent = $lead->latestConsent;
                $match = $this->partnerMatcher->match($lead, collect([$partner]))[0];

                $row = $this->referralRepository->createReferral([
                    'lead_id' => $lead->id,
                    'partner_id' => $partner->id,
                    'referred_by' => $by->id,
                    'message' => $this->contractMessage($contract),
                    'score' => $match['score'],
                    'covers' => $match['covers'],
                    'consent_text' => $consent->text,
                    'consent_version' => $consent->version,
                    'consent_given_at' => $consent->given_at,
                    'status' => ReferralStatus::Enviado->value,
                    'contract_id' => $contract->id,
                    'subject_key' => Referral::contractSubject($contract->id),
                ]);

                $this->leadService->recordReferral($lead, [
                    'referral_id' => $row->id,
                    'partner_id' => $partner->id,
                    'partner' => $partner->name,
                    'specialty' => $partner->specialty,
                    'score' => $match['score'],
                    'contract_id' => $contract->id,
                ], $by);

                return $row->setRelation('lead', $lead);
            });
        } catch (UniqueConstraintViolationException) {
            throw ReferralException::alreadyReferred();
        }

        Mail::to($partner->email)->queue((new ReferralSent($this->mail($referral->lead, $partner, $referral, $by)))->locale(self::MAIL_LOCALE));

        return $this->referralRepository->loadReferralForTrail($referral);
    }

    /** @return LengthAwarePaginator<int, Referral> the trail (R23), newest first */
    public function trail(?int $partnerId, int $perPage): LengthAwarePaginator
    {
        return $this->referralRepository->paginateReferrals($partnerId, $perPage);
    }

    /** One client of a batch: its lead, the referral with the lead's consent copied, the timeline line. */
    private function referPortfolioClient(Client $client, DiagnosticProfileDTO $profile, Partner $partner, DiagnosticRule $rule, User $by): Referral
    {
        $lead = $this->leadService->findOrStartPortfolioLead($client, $profile);
        $consent = $lead->latestConsent;
        $match = $this->partnerMatcher->match($lead, collect([$partner]))[0];

        $referral = $this->referralRepository->createReferral([
            'lead_id' => $lead->id,
            'partner_id' => $partner->id,
            'referred_by' => $by->id,
            'message' => $this->batchMessage($client, $rule),
            'score' => $match['score'],
            'covers' => $match['covers'],
            'consent_text' => $consent->text,
            'consent_version' => $consent->version,
            'consent_given_at' => $consent->given_at,
            'status' => ReferralStatus::Enviado->value,
        ]);

        $this->leadService->recordReferral($lead, [
            'referral_id' => $referral->id,
            'partner_id' => $partner->id,
            'partner' => $partner->name,
            'specialty' => $partner->specialty,
            'score' => $match['score'],
        ], $by);

        return $referral->setRelation('lead', $lead);
    }

    /**
     * The demo's Radar text (`referBatch`), in pt-BR as the partner reads it: the firm stays the
     * client's owner. "Desde" is the year the client's consent was signed (its contract), else the
     * year it entered the portfolio.
     */
    private function batchMessage(Client $client, DiagnosticRule $rule): string
    {
        $firm = (string) ($this->currentFirm->get()->name ?? config('rnc.brand.name'));
        $since = ($client->partner_consent_at ?? $client->created_at)->setTimezone(self::LOCAL_TIMEZONE)->year;

        return sprintf(
            'A %1$s indica a %2$s, cliente da carteira desde %3$d: o Radar da Reforma apontou “%4$s”. Podem fazer o primeiro contato em até %5$d horas úteis? A %1$s segue responsável pelo cliente.',
            $firm,
            $client->name,
            $since,
            $rule->title(),
            ReferralSla::HOURS,
        );
    }

    /**
     * The demo's contract text (`ctRefer`), in pt-BR as the partner reads it — with the annual value
     * as its band, never the value itself (AM20): the client's prices are Franco's.
     */
    private function contractMessage(ClientContract $contract): string
    {
        $firm = (string) ($this->currentFirm->get()->name ?? config('rnc.brand.name'));

        return sprintf(
            'A %1$s indica a %2$s: o contrato de %3$s de %4$s por ano vai até %5$d sem cláusula de reequilíbrio para a Reforma. Podem revisar e propor o aditivo? A %1$s segue responsável pelo cliente.',
            $firm,
            $contract->client->name,
            mb_strtolower(ContractKind::from($contract->kind)->label()),
            ContractValueBand::of($contract->annual_value_cents)['label'],
            $contract->end_year,
        );
    }

    /**
     * Client id → the partners its `carteira` lead was already sent to (as keys).
     *
     * @return array<int, array<int, true>>
     */
    private function referredPartnersByClient(): array
    {
        $leadIds = $this->leadRepository->findPortfolioLeadIdsByClient();
        $partnerIds = $this->referralRepository->findPartnerIdsByLeads(array_values($leadIds));
        $referred = [];

        foreach ($leadIds as $clientId => $leadId) {
            $referred[$clientId] = array_fill_keys($partnerIds[$leadId] ?? [], true);
        }

        return $referred;
    }

    /**
     * @param  list<Client>  $clients  in the Radar's order
     * @param  array<int, array<int, true>>  $referred
     * @return array{batch: list<Client>, skipped_no_consent: int, skipped_already: int}
     */
    private static function pickBatch(array $clients, int $partnerId, array $referred): array
    {
        $consented = array_values(array_filter($clients, fn (Client $client) => $client->partner_consent === true));
        $fresh = array_values(array_filter($consented, fn (Client $client) => ! isset($referred[$client->id][$partnerId])));

        return [
            'batch' => array_slice($fresh, 0, self::BATCH_SIZE),
            'skipped_no_consent' => count($clients) - count($consented),
            'skipped_already' => count($consented) - count($fresh),
        ];
    }

    private static function covers(Partner $partner, DiagnosticRule $rule): bool
    {
        return in_array($rule, $partner->specialtyCase()->covers(), true);
    }

    private function blockedReason(Lead $lead): ?string
    {
        $consent = $lead->latestConsent;

        if (! $consent) {
            return self::BLOCKED_CONSENT_MISSING;
        }

        return $consent->given ? null : self::BLOCKED_CONSENT_REFUSED;
    }

    /** The partner's e-mail, as values (AM6): the company, never the lead's contact (R22). */
    private function mail(Lead $lead, Partner $partner, Referral $referral, User $by): ReferralMailDTO
    {
        $firm = $this->currentFirm->get();

        return new ReferralMailDTO(
            partnerName: $partner->name,
            specialty: $partner->specialtyCase()->label(),
            company: (string) $lead->company,
            sector: $lead->profile['sector'] ?? null,
            covers: array_column($referral->covers, 'title'),
            message: $referral->message,
            referredBy: trim($by->name.' '.$by->last_name),
            firmName: $firm->name ?? (string) config('rnc.brand.name'),
            firmPhone: $firm?->phone,
            firmMailbox: $firm?->mailbox,
            firmAddress: $firm?->address,
        );
    }
}
