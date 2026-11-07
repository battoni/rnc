<?php

namespace App\Modules\Partner\Services;

use App\Models\User;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Partner\DTOs\ReferLeadDTO;
use App\Modules\Partner\DTOs\ReferralMailDTO;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Exceptions\ReferralException;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Repositories\PartnerRepository;
use App\Modules\Partner\Repositories\ReferralRepository;
use Illuminate\Database\QueryException;
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

    private const INTEGRITY_CONSTRAINT_VIOLATION = '23000';

    private const PARTNER_UNAVAILABLE = 'partners.routing.partnerUnavailable';

    public const BLOCKED_CONSENT_MISSING = 'consent_missing';

    public const BLOCKED_CONSENT_REFUSED = 'consent_refused';

    public function __construct(
        private CurrentFirm $currentFirm,
        private LeadRepository $leadRepository,
        private LeadService $leadService,
        private PartnerRepository $partnerRepository,
        private ReferralRepository $referralRepository,
        private PartnerMatcher $partnerMatcher,
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
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::INTEGRITY_CONSTRAINT_VIOLATION) {
                throw $exception;
            }

            throw ReferralException::alreadyReferred();
        }

        Mail::to($partner->email)->queue((new ReferralSent($this->mail($lead, $partner, $written['referral'], $by)))->locale(self::MAIL_LOCALE));

        return ['referral' => $this->referralRepository->loadReferralForTrail($written['referral']), 'event' => $written['event']];
    }

    /** @return LengthAwarePaginator<int, Referral> the trail (R23), newest first */
    public function trail(?int $partnerId, int $perPage): LengthAwarePaginator
    {
        return $this->referralRepository->paginateReferrals($partnerId, $perPage);
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
