<?php

namespace App\Modules\Partner\Services;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Helpers\BusinessHours;
use App\Modules\Partner\Models\Referral;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The first-contact SLA of a referral (rnc-2 R8; the demo's `referralSla`): 24 business hours of the
 * firm from the referral to the first contact. While nobody has called it is `ok` (< 12 h),
 * `atencao` (12–24) or `estourado` (≥ 24); once contacted the clock stops — `cumprido` (≤ 24) or
 * `fora`; a declined referral is `encerrado`. The state reads the exact hours; the answer carries
 * them to one decimal, as the chip shows them.
 */
class ReferralSla
{
    public const HOURS = 24;

    public const WARN_HOURS = 12;

    public const OK = 'ok';

    public const WARNING = 'atencao';

    public const BREACHED = 'estourado';

    public const MET = 'cumprido';

    public const MISSED = 'fora';

    public const CLOSED = 'encerrado';

    private const DECIMALS = 1;

    public function __construct(private CurrentFirm $currentFirm) {}

    /** @return array{state: string, hours: float} the referral's clock now, on the current firm's week */
    public function of(Referral $referral): array
    {
        return self::measure(
            ReferralStatus::from($referral->status),
            $referral->created_at,
            $referral->contacted_at,
            CarbonImmutable::now(),
            new BusinessHours($this->currentFirm->get()->business_hours ?? []),
        );
    }

    /** @return array{state: string, hours: float} */
    public static function measure(ReferralStatus $status, CarbonInterface $sentAt, ?CarbonInterface $contactedAt, CarbonInterface $now, BusinessHours $week): array
    {
        if ($status === ReferralStatus::Recusado) {
            return ['state' => self::CLOSED, 'hours' => 0.0];
        }

        if ($contactedAt !== null) {
            $hours = $week->between($sentAt, $contactedAt);

            return ['state' => $hours <= self::HOURS ? self::MET : self::MISSED, 'hours' => round($hours, self::DECIMALS)];
        }

        $hours = $week->between($sentAt, $now);
        $state = match (true) {
            $hours >= self::HOURS => self::BREACHED,
            $hours >= self::WARN_HOURS => self::WARNING,
            default => self::OK,
        };

        return ['state' => $state, 'hours' => round($hours, self::DECIMALS)];
    }
}
