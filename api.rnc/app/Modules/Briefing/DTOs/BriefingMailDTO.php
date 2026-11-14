<?php

namespace App\Modules\Briefing\DTOs;

use App\Modules\Briefing\Helpers\ActionPicker;
use App\Modules\Briefing\Services\BriefingService;
use App\Modules\Firm\Services\MailBrand;
use Carbon\CarbonImmutable;

/**
 * What the 07:00 e-mail prints (rnc-3 R27, AM6): the firm's briefing as `GET briefing` answers it,
 * built under the firm before queueing, and who it greets — plain values, so the queued Mailable
 * never queries (a worker has no current firm). The figures speak pt-BR here, as the e-mails do.
 *
 * @phpstan-import-type Briefing from BriefingService
 */
final class BriefingMailDTO
{
    public const SCREEN = '/gestao';

    private const MILLION = 1_000_000;

    private const THOUSAND = 1_000;

    private const CENTS_PER_UNIT = 100;

    /** Radar's `period` of a range held per month (`split-payment`). */
    private const RADAR_MONTHLY = 'mes';

    /** @param  Briefing  $briefing */
    public function __construct(
        public readonly string $firstName,
        public readonly string $firmName,
        public readonly string $frontendUrl,
        public readonly array $briefing,
        public readonly MailBrand $brand,
    ) {}

    /** "sexta-feira, 13 de novembro" — the day the briefing is of, in São Paulo. */
    public function day(): string
    {
        return CarbonImmutable::parse($this->briefing['as_of'])->locale('pt_BR')->translatedFormat('l, d \\d\\e F');
    }

    /**
     * One action's sentence (R25, the demo's copy) from its kind and numbers.
     *
     * @param  array{kind: string, params: array<string, mixed>, to: string}  $action
     */
    public function actionLine(array $action): string
    {
        $params = $action['params'];

        return match ($action['kind']) {
            ActionPicker::SLA_BREACHED => __('mail.briefing.actions.sla_breached', [
                'partner' => $params['partner'], 'company' => $params['company'],
                'hours' => self::hours($params['hours']), 'goal' => $params['goal_hours'],
            ]),
            ActionPicker::PROPOSALS => is_array($params['range_cents'])
                ? trans_choice('mail.briefing.actions.proposals', $params['count'], ['range' => self::range($params['range_cents'])])
                : trans_choice('mail.briefing.actions.proposals_no_range', $params['count']),
            ActionPicker::RADAR => trans_choice('mail.briefing.actions.'.($params['period'] === self::RADAR_MONTHLY ? 'radar_monthly' : 'radar'), $params['clients'], [
                'title' => $params['title'], 'range' => self::range($params['range_cents']),
            ]),
            default => trans_choice('mail.briefing.actions.'.$action['kind'], $params['count']),
        };
    }

    /** The briefing's own screen, the e-mail's button (R27). */
    public function link(): string
    {
        return $this->url(self::SCREEN);
    }

    /** A screen of the SPA — an action's `to` or the briefing itself. */
    public function url(string $path): string
    {
        return rtrim($this->frontendUrl, '/').$path;
    }

    /** "qui 19/11 · 14:00" — a meeting's start, already in São Paulo. */
    public static function when(string $iso): string
    {
        return CarbonImmutable::parse($iso)->locale('pt_BR')->translatedFormat('D d/m · H:i');
    }

    /** The pipeline's range, or the words for an empty portfolio. */
    public function pipelineRange(): string
    {
        $range = $this->briefing['pipeline']['range_cents'];

        return $range === null ? __('mail.briefing.kpi.no_base') : self::range($range);
    }

    /**
     * An annual range as the demo's `formatRange` speaks it: "R$ 54–108 mil", from a million reais
     * "R$ 1,2–3,4 mi" — an estimate, so no cents.
     *
     * @param  array{0: int, 1: int}  $cents
     */
    public static function range(array $cents): string
    {
        [$low, $high] = [$cents[0] / self::CENTS_PER_UNIT, $cents[1] / self::CENTS_PER_UNIT];

        if ($high >= self::MILLION) {
            return 'R$ '.number_format($low / self::MILLION, 1, ',', '.').'–'.number_format($high / self::MILLION, 1, ',', '.').' mi';
        }

        $from = max(1, (int) round($low / self::THOUSAND));
        $to = max($from + 1, (int) round($high / self::THOUSAND));

        return 'R$ '.number_format($from, 0, ',', '.').'–'.number_format($to, 0, ',', '.').' mil';
    }

    /** "31,5" or "32" — business hours as the SLA chip shows them, one decimal at most. */
    public static function hours(float|int $hours): string
    {
        $text = number_format((float) $hours, 1, ',', '.');

        return str_ends_with($text, ',0') ? substr($text, 0, -2) : $text;
    }
}
