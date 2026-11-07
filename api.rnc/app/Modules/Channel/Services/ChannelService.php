<?php

namespace App\Modules\Channel\Services;

use App\Models\User;
use App\Modules\Channel\DTOs\ChannelFirmDTO;
use App\Modules\Channel\DTOs\UpdateChannelFirmDTO;
use App\Modules\Channel\Enums\ChannelStage;
use App\Modules\Channel\Exceptions\ChannelException;
use App\Modules\Channel\Helpers\ChannelShare;
use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Channel\Repositories\ChannelRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Franco's RNC channel (rnc-3 R6–R8, D5, D6): the firms on their way to the platform, Franco's share
 * of the active ones' fees, and the one-step-forward move. Nothing is billed — every amount is "a
 * conferir". The board answers in two queries whatever the number of firms (plan P13).
 *
 * @phpstan-type Summary array{monthly_share_cents: int, yearly_share_cents: int, pending_monthly_share_cents: int, counts: array<string, int>}
 * @phpstan-type Board array{summary: Summary, firms: Collection<int, ChannelFirm>, defaults: array{monthly_fee_cents: int, share_bp: int}}
 */
class ChannelService
{
    private const MONTHS_PER_YEAR = 12;

    public function __construct(private ChannelRepository $channelRepository) {}

    /** @return Board */
    public function board(): array
    {
        return [
            'summary' => $this->summary(),
            'firms' => $this->channelRepository->findAllChannelFirms(),
            'defaults' => self::defaults(),
        ];
    }

    /** A new firm at contato, on its own fee and share or the defaults (R7). */
    public function create(ChannelFirmDTO $firm): ChannelFirm
    {
        $defaults = self::defaults();

        return $this->channelRepository->createChannelFirm([
            'name' => $firm->name,
            'city' => $firm->city,
            'notes' => $firm->notes,
            'monthly_fee_cents' => $firm->monthly_fee_cents ?? $defaults['monthly_fee_cents'],
            'share_bp' => $firm->share_bp ?? $defaults['share_bp'],
            'stage' => ChannelStage::Contato->value,
            'stage_at' => CarbonImmutable::now(),
        ]);
    }

    /** @return ChannelFirm|null null for an unknown or another firm's channel firm */
    public function update(int $id, UpdateChannelFirmDTO $changes): ?ChannelFirm
    {
        $firm = $this->channelRepository->findChannelFirmById($id);

        return ! $firm ? null : $this->channelRepository->updateChannelFirm($firm, $changes->toArray());
    }

    /**
     * One stage forward (R7), and the event that records it, in one transaction on the locked row.
     * Any other move — a skip, a step back, staying put, out of ativo — is 422 `invalid_transition`.
     *
     * @return ChannelFirm|null null for an unknown or another firm's channel firm
     */
    public function advance(int $id, ChannelStage $to, User $by): ?ChannelFirm
    {
        return DB::transaction(function () use ($id, $to, $by) {
            $firm = $this->channelRepository->findChannelFirmForUpdate($id);

            if (! $firm) {
                return null;
            }

            $from = ChannelStage::from($firm->stage);

            if (! $from->canTransitionTo($to)) {
                throw ChannelException::invalidTransition();
            }

            $now = CarbonImmutable::now();
            $this->channelRepository->updateChannelFirm($firm, ['stage' => $to->value, 'stage_at' => $now]);
            $this->channelRepository->createChannelFirmEvent([
                'channel_firm_id' => $firm->id,
                'user_id' => $by->id,
                'from' => $from->value,
                'to' => $to->value,
                'at' => $now,
            ]);

            return $firm;
        });
    }

    /** @return array{monthly_fee_cents: int, share_bp: int} what a new firm starts with (D6) */
    public static function defaults(): array
    {
        return [
            'monthly_fee_cents' => (int) config('channel.monthly_fee_cents'),
            'share_bp' => (int) config('channel.share_bp'),
        ];
    }

    /**
     * The hero and the stage counts (R8): Σ of the active firms' shares a month and a year, Σ of the
     * others' once they activate, and how many firms sit at each stage — each share rounded half-up
     * per firm, then summed.
     *
     * @return Summary
     */
    private function summary(): array
    {
        $counts = array_fill_keys(ChannelStage::values(), 0);
        $active = [];
        $pending = [];

        foreach ($this->channelRepository->summarizeChannelFirms() as $group) {
            $counts[$group['stage']] = ($counts[$group['stage']] ?? 0) + $group['firm_count'];
            $line = [$group['monthly_fee_cents'], $group['share_bp'], $group['firm_count']];

            if ($group['stage'] === ChannelStage::Ativo->value) {
                $active[] = $line;
            } else {
                $pending[] = $line;
            }
        }

        $monthly = ChannelShare::sum($active);

        return [
            'monthly_share_cents' => $monthly,
            'yearly_share_cents' => $monthly * self::MONTHS_PER_YEAR,
            'pending_monthly_share_cents' => ChannelShare::sum($pending),
            'counts' => $counts,
        ];
    }
}
