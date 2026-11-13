<?php

namespace App\Modules\Partner\DTOs;

use App\Modules\Partner\Models\Partner;

/**
 * A login just created or a password just regenerated: the partner (its `account` loaded) and
 * the password in clear, which exists only in this response (R2) and is never stored.
 */
final readonly class IssuedPartnerAccess
{
    public function __construct(
        public Partner $partner,
        public string $password,
    ) {}
}
