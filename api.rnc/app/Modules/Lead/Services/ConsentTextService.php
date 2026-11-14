<?php

namespace App\Modules\Lead\Services;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\FirmConsentVersion;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Repositories\FirmConsentVersionRepository;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The words of a firm's partner-introduction consent (rnc-4 R13, D12), the only writer of
 * `firm_consent_versions`. A firm's current text is its highest version; a new text is version n+1
 * and an older one is never touched. A firm with no version yet starts at 1, copied from the
 * platform default (`PlatformSettings::consentDefault`) the first time anyone needs it — the seeders
 * and `firm:create` (F3b1) start it on purpose, this keeps a firm made any other way from answering
 * without words.
 *
 * What a visitor reads and what `lead_consents` stores is the text with `{escritorio}` filled by the
 * name the visitor met: the lead's sub-brand's (rnc-3 R3), else the firm's.
 */
class ConsentTextService
{
    private const FIRST_VERSION = 1;

    /** Tries at a new version number when another write took the one this one counted. */
    private const VERSION_ATTEMPTS = 3;

    public function __construct(
        private FirmConsentVersionRepository $firmConsentVersionRepository,
        private PlatformSettings $platformSettings,
    ) {}

    /** The firm's current version, version 1 from the platform default when it has none. */
    public function currentVersion(int $firmId): FirmConsentVersion
    {
        return $this->firmConsentVersionRepository->findLatestFirmConsentVersion($firmId) ?? $this->startFirm($firmId);
    }

    /**
     * Version 1 of a firm that has none: `$text`, else the platform default. A firm that already has
     * a version keeps it — this never rewrites words someone answered under.
     */
    public function startFirm(int $firmId, ?string $text = null): FirmConsentVersion
    {
        $latest = $this->firmConsentVersionRepository->findLatestFirmConsentVersion($firmId);

        if ($latest) {
            return $latest;
        }

        try {
            return $this->firmConsentVersionRepository->createFirmConsentVersion($firmId, self::FIRST_VERSION, $text ?? $this->platformSettings->consentDefault(), null);
        } catch (UniqueConstraintViolationException $exception) {
            // Another request started the firm first: its version 1 is the answer.
            return $this->firmConsentVersionRepository->findLatestFirmConsentVersion($firmId) ?? throw $exception;
        }
    }

    /** A new text: version n+1 of the firm (a firm with none is started at 1 first). */
    public function createVersion(int $firmId, string $text, ?int $authorUserId): FirmConsentVersion
    {
        for ($attempt = 1; ; $attempt++) {
            $next = $this->currentVersion($firmId)->version + 1;

            try {
                return $this->firmConsentVersionRepository->createFirmConsentVersion($firmId, $next, $text, $authorUserId);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::VERSION_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * What this lead's visitor reads and answers: its firm's current version, `{escritorio}` filled
     * with its sub-brand's name, else its firm's.
     *
     * @return array{text: string, version: int}
     */
    public function forLead(Lead $lead): array
    {
        $current = $this->currentVersion($lead->firm_id);

        return ['text' => ConsentTerms::fill($current->text, $lead->brand->name ?? (string) $lead->firm?->name), 'version' => $current->version];
    }

    /**
     * What a visitor reads on a form with no lead behind it yet (rnc-4 R29: the beta signup): the
     * firm's current version, `{escritorio}` filled with the firm's name.
     *
     * @return array{text: string, version: int}
     */
    public function forFirm(Firm $firm): array
    {
        $current = $this->currentVersion($firm->id);

        return ['text' => ConsentTerms::fill($current->text, $firm->name), 'version' => $current->version];
    }

    /**
     * The consent a portfolio client gave in its service contract (rnc-2 R27): one generic sentence,
     * not versioned per firm (D12), the firm's name filled.
     *
     * @return array{text: string, version: int}
     */
    public function contractConsent(Firm $firm): array
    {
        return ['text' => ConsentTerms::fill(ConsentTerms::CONTRACT_TEXT, $firm->name), 'version' => ConsentTerms::CONTRACT_VERSION];
    }
}
