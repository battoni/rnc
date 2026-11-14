<?php

namespace App\Modules\Lead\Repositories;

use App\Modules\Firm\Scopes\FirmScope;
use App\Modules\Lead\Models\FirmConsentVersion;

/**
 * Every read names its firm itself (`firm_id`) beside lifting the scope: a lead's consent is its own
 * firm's, whatever the request's, and a seeder or a command may ask for a firm it is not running as.
 */
class FirmConsentVersionRepository
{
    public function findLatestFirmConsentVersion(int $firmId): ?FirmConsentVersion
    {
        return FirmConsentVersion::withoutGlobalScope(FirmScope::class)
            ->where('firm_id', $firmId)
            ->orderByDesc('version')
            ->first();
    }

    public function createFirmConsentVersion(int $firmId, int $version, string $text, ?int $authorUserId): FirmConsentVersion
    {
        return FirmConsentVersion::query()->create([
            'firm_id' => $firmId,
            'version' => $version,
            'text' => $text,
            'author_user_id' => $authorUserId,
        ]);
    }
}
