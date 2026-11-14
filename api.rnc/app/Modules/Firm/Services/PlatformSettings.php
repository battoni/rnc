<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Repositories\PlatformSettingRepository;

/**
 * The platform's own settings (rnc-4 D12), the only writer of `platform_settings`. Today one: the
 * consent sentence a new firm's version 1 copies (`consent_default`, R13). An unset key reads the
 * built-in default, so a database seeded before the row existed still answers. The operator edits it
 * from the console (F4a, `platform/consent-default`).
 */
class PlatformSettings
{
    public const CONSENT_DEFAULT = 'consent_default';

    /** The platform's own words: no firm and no network named, the firm comes in through `{escritorio}`. */
    public const DEFAULT_CONSENT_TEXT = 'Autorizo {escritorio} a me apresentar a especialistas parceiros quando o diagnóstico indicar.';

    public function __construct(private PlatformSettingRepository $platformSettingRepository) {}

    public function consentDefault(): string
    {
        return $this->platformSettingRepository->findPlatformSettingValue(self::CONSENT_DEFAULT) ?? self::DEFAULT_CONSENT_TEXT;
    }

    /**
     * The operator's edit (rnc-4 F4a, R13, R25): what the NEXT firm's version 1 copies. Firms that
     * already have versions keep them — their consent changes only through their own new version.
     */
    public function updateConsentDefault(string $text): string
    {
        return $this->platformSettingRepository->updateOrCreatePlatformSetting(self::CONSENT_DEFAULT, $text)->value;
    }

    /** The built-in default written once; an operator's edit (F4a) survives every later seed. */
    public function startConsentDefault(): void
    {
        $this->platformSettingRepository->firstOrCreatePlatformSetting(self::CONSENT_DEFAULT, self::DEFAULT_CONSENT_TEXT);
    }
}
