<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Database\Seeder;

/**
 * The platform firm (rnc-4 D3): the row the root host and `www` serve and the operator, who has no
 * firm, works in. Every environment — without it a signed-in operator is refused with
 * `firms.required` (plan AM1). Named after the platform (`PLATFORM_NAME`); its look is the
 * platform's own until the console edits it, and a re-seed never undoes that edit (verify P1-C1).
 *
 * rnc-4 F2a (R13, D12): the platform's default consent (`platform_settings.consent_default`) is
 * written here, every environment, and the platform firm's version 1 copies it.
 */
class PlatformFirmSeeder extends Seeder
{
    public const PLATFORM_SLUG = 'hub';

    /**
     * What the root's only form — the beta signup (R29) — asks consent for (rnc-4 F6b, W66): contact
     * about the beta, not the partner introduction every firm's version 1 copies. Written as the
     * platform firm's next version, once; the operator's later edits stay theirs.
     */
    public const BETA_CONSENT = 'Autorizo a equipe do {escritorio} a entrar em contato comigo e com o meu escritório por e-mail, telefone ou WhatsApp sobre o programa beta, e a guardar estes dados só para isso, conforme a LGPD.';

    private const FIRST_CONSENT_VERSION = 1;

    private const PRIMARY = '#1f4e79';

    private const ACCENT = '#f2a900';

    private const SHORT_NAME_LENGTH = 40;

    private const BUSINESS_HOURS = [
        ['weekdays' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00'],
    ];

    public function run(PlatformSettings $platformSettings, ConsentTextService $consentTextService): void
    {
        $name = (string) config('platform.name');

        $platformSettings->startConsentDefault();

        // Created once, never rewritten (verify P1-C1): `db:seed` runs on every deploy, and the console's
        // edits (look, modules, contact) are the operator's from then on. Only `is_platform` is enforced.
        $firm = Firm::firstOrCreate(
            ['slug' => self::PLATFORM_SLUG],
            [
                'name' => $name,
                'logo_text' => $name,
                'short_name' => mb_substr($name, 0, self::SHORT_NAME_LENGTH),
                'brand_primary' => self::PRIMARY,
                'brand_accent' => self::ACCENT,
                'business_hours' => self::BUSINESS_HOURS,
                'is_platform' => true,
                // Core modules only (R23): the platform's own firm runs the site and the pipeline.
                'modules' => [],
            ]
        );

        if (! $firm->is_platform) {
            $firm->forceFill(['is_platform' => true])->save();
        }

        $first = $consentTextService->startFirm($firm->id);

        // Only over the copied version 1: a re-seed never stacks a version on the operator's own words.
        if ($first->version === self::FIRST_CONSENT_VERSION) {
            $consentTextService->createVersion($firm->id, self::BETA_CONSENT, null);
        }
    }
}
