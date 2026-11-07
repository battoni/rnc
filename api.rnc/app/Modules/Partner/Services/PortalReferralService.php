<?php

namespace App\Modules\Partner\Services;

use App\Models\User;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Notification\Notifications\ReferralStatusNotification;
use App\Modules\Notification\Services\TeamNotifier;
use App\Modules\Partner\DTOs\TransitionReferralDTO;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Exceptions\ReferralException;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Repositories\PortalReferralRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The partner's side of a referral (rnc-2 R5–R9): its inbox and its moves. Every row comes from
 * `PortalReferralRepository`, so it is the current firm's and the signed-in partner's — anything
 * else is null, the controller's 404 (R4). A move follows `ReferralStatus`'s graph under the row's
 * lock, stores who and when, writes the lead's `encaminhamento.status` line through `LeadService`
 * and, on aceito, recusado and fechado, rings the team's bell. The lead's stage never moves (R9).
 */
class PortalReferralService
{
    /** The moves Franco's team hears in the bell (R9); the others only reach the timeline. */
    private const RINGS_THE_BELL = [ReferralStatus::Aceito, ReferralStatus::Recusado, ReferralStatus::Fechado];

    public function __construct(
        private PortalReferralRepository $portalReferralRepository,
        private LeadService $leadService,
        private TeamNotifier $teamNotifier,
    ) {}

    /** @return Collection<int, Referral> the inbox (R5): open before closed, newest first within each */
    public function inbox(): Collection
    {
        return $this->portalReferralRepository->findPartnerReferrals()
            ->sortBy(fn (Referral $referral) => ReferralStatus::from($referral->status)->isTerminal() ? 1 : 0)
            ->values();
    }

    public function show(int $id): ?Referral
    {
        return $this->portalReferralRepository->findPartnerReferralById($id);
    }

    /** One step of the graph (R7) by the partner's login; 422 `invalid_transition` off it. */
    public function transition(int $id, TransitionReferralDTO $move, User $by): ?Referral
    {
        $moved = DB::transaction(function () use ($id, $move, $by) {
            $referral = $this->portalReferralRepository->findPartnerReferralForUpdate($id);

            if (! $referral) {
                return null;
            }

            $from = ReferralStatus::from($referral->status);

            if (! $from->canTransitionTo($move->to)) {
                throw ReferralException::invalidTransition();
            }

            $this->portalReferralRepository->updateReferral($referral, $this->attributesOf($move, $by));
            $this->leadService->recordReferralStatus($referral->lead, $this->lineOf($referral, $from), $by);

            return $referral;
        });

        if ($moved && in_array($move->to, self::RINGS_THE_BELL, true)) {
            $this->teamNotifier->notifyTeam(new ReferralStatusNotification(
                leadId: (int) $moved->lead_id,
                referralId: $moved->id,
                company: (string) $moved->lead->company,
                partner: $moved->partner->name,
                status: $move->to->value,
                dealValueCents: $moved->deal_value_cents,
            ));
        }

        return $moved;
    }

    /** @return array<string, mixed> the status, who and when, and the step's own fields */
    private function attributesOf(TransitionReferralDTO $move, User $by): array
    {
        $now = CarbonImmutable::now();
        $attributes = ['status' => $move->to->value, 'status_at' => $now, 'status_by' => $by->id];

        return match ($move->to) {
            ReferralStatus::Contato => [...$attributes, 'contacted_at' => $now],
            ReferralStatus::Reuniao => [...$attributes, 'meeting_at' => CarbonImmutable::parse((string) $move->meeting_at)],
            ReferralStatus::Fechado => [...$attributes, 'deal_value_cents' => $move->deal_value_cents, 'closed_at' => $now],
            default => $attributes,
        };
    }

    /**
     * The timeline line's data: which referral, whose, from where to where, and the meeting or the
     * value when the step has one.
     *
     * @return array{referral_id: int, partner_id: int, partner: string, from: string, to: string, meeting_at?: string, deal_value_cents?: int}
     */
    private function lineOf(Referral $referral, ReferralStatus $from): array
    {
        return [
            'referral_id' => $referral->id,
            'partner_id' => (int) $referral->partner_id,
            'partner' => $referral->partner->name,
            'from' => $from->value,
            'to' => $referral->status,
            ...($referral->status === ReferralStatus::Reuniao->value ? ['meeting_at' => $referral->meeting_at?->utc()->toIso8601String()] : []),
            ...($referral->status === ReferralStatus::Fechado->value ? ['deal_value_cents' => $referral->deal_value_cents] : []),
        ];
    }
}
