<?php

namespace App\Modules\Firm\DTOs;

use App\Models\User;
use App\Modules\Firm\Models\Firm;

/**
 * What `FirmProvisioner` made: the firm, its manager and the manager's one-time password — shown
 * once by whoever asked (`firm:create` prints it) and stored nowhere but as the hash.
 */
final readonly class ProvisionedFirm
{
    public function __construct(
        public Firm $firm,
        public User $manager,
        public string $password,
    ) {}
}
