<?php

namespace App\Modules\Firm\Helpers;

use App\Modules\Firm\Models\Firm;

/**
 * The module catalogue (rnc-4 R16, D15, D16): what a firm can turn on, what it always has, and what
 * needs what. Pure — the firm's own list is `firms.modules`, written by `FirmModulesService` only.
 *
 * - **Core** is code, never stored: every firm has the site, the diagnostic, the pipeline, the
 *   bookings and the metrics.
 * - **Opt-in** keys are stored on the firm; a key no longer in the catalogue reads as off.
 * - **Operator-only** keys (`channel`, the partner network's channel — one tenant's) are opt-in keys
 *   only the platform operator may turn on or off.
 * - **Dependencies**: `radar`, `contracts` and `fiscal` read the portfolio's clients.
 */
final class FirmModules
{
    /** What a module that is off answers: 404 on its routes, 422 on a field that asks for it. */
    public const DISABLED = 'modules.disabled';

    public const PORTFOLIO = 'portfolio';

    public const FISCAL = 'fiscal';

    public const RADAR = 'radar';

    public const PARTNERS = 'partners';

    public const CONTRACTS = 'contracts';

    public const CHAT = 'chat';

    public const ASSISTANT = 'assistant';

    public const BRIEFING = 'briefing';

    public const BRANDS = 'brands';

    public const CHANNEL = 'channel';

    /** Always on, never stored. */
    public const CORE = ['firm', 'auth', 'site', 'content', 'tracking', 'diagnostic', 'lead', 'booking', 'notification', 'pipeline', 'metrics'];

    /** A firm's own choice, in the order every stored list is written. */
    public const OPT_IN = [self::PORTFOLIO, self::FISCAL, self::RADAR, self::PARTNERS, self::CONTRACTS, self::CHAT, self::ASSISTANT, self::BRIEFING, self::BRANDS];

    /** Opt-in keys only the platform operator turns on or off. */
    public const OPERATOR_ONLY = [self::CHANNEL];

    /** Every storable key, in the stored order. */
    public const ALL = [...self::OPT_IN, ...self::OPERATOR_ONLY];

    /** @var array<string, list<string>> a key → the keys it needs on */
    public const DEPENDENCIES = [
        self::RADAR => [self::PORTFOLIO],
        self::CONTRACTS => [self::PORTFOLIO],
        self::FISCAL => [self::PORTFOLIO],
    ];

    public static function isCore(string $module): bool
    {
        return in_array($module, self::CORE, true);
    }

    public static function isEnabled(Firm $firm, string $module): bool
    {
        return self::isCore($module) || in_array($module, self::enabled($firm), true);
    }

    /**
     * The firm's opt-in keys the catalogue still knows, in the catalogue's order.
     *
     * @return list<string>
     */
    public static function enabled(Firm $firm): array
    {
        return self::normalise((array) ($firm->modules ?? []));
    }

    /**
     * Known storable keys only, once each, in the catalogue's order.
     *
     * @param  array<int, mixed>  $modules
     * @return list<string>
     */
    public static function normalise(array $modules): array
    {
        return array_values(array_filter(self::ALL, fn (string $key) => in_array($key, $modules, true)));
    }
}
