<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Partner\Enums\PartnerAccessEventType;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\PartnerAccessEvent;
use App\Modules\Partner\Services\PartnerAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * The partners' portal logins (rnc-2 F1, R1), outside production only: `parceiro.1`…`parceiro.8`,
 * one per seeded "(exemplo)" partner in `PartnerSeeder`'s order, role `parceiro`, in the partner's
 * firm (AM1), password `seeding.password` — the shared local default, which is why it refuses a
 * production database. Each gets the granted event and `access_granted_at` the service would write
 * (AM9), dated when the seed ran.
 *
 * After `PartnerSeeder` (it needs the partners). Runs inside `CurrentFirm::runAs` and writes
 * `firm_id` itself — `DatabaseSeeder` runs without model events (rnc-1 pitfall).
 */
class PartnerUserSeeder extends Seeder
{
    private const USERNAME_PREFIX = 'parceiro.';

    private const EMAIL_DOMAIN = 'parceiro.exemplo';

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();
        $roleId = Role::where('slug', PartnerAccessService::PARTNER_ROLE_SLUG)->value('id');

        if (! $firm || ! $roleId) {
            return;
        }

        $currentFirm->runAs($firm, fn () => $this->seedFirm($firm, (int) $roleId));
    }

    private function seedFirm(Firm $firm, int $roleId): void
    {
        $now = CarbonImmutable::now();

        foreach (Partner::query()->orderBy('id')->get()->values() as $index => $partner) {
            $number = $index + 1;

            $login = User::updateOrCreate(
                ['username' => self::USERNAME_PREFIX.$number],
                [
                    'name' => $partner->name,
                    'last_name' => '',
                    'email' => 'portal'.$number.'@'.self::EMAIL_DOMAIN,
                    'role_id' => $roleId,
                    'firm_id' => $firm->id,
                    'partner_id' => $partner->id,
                    // Plaintext on purpose: the `hashed` cast on User::password hashes it on save.
                    'password' => config('seeding.password'),
                ]
            );

            PartnerAccessEvent::query()->create([
                'firm_id' => $firm->id,
                'partner_id' => $partner->id,
                'user_id' => $login->id,
                'type' => PartnerAccessEventType::Granted->value,
                'at' => $now,
            ]);

            $partner->update(['access_granted_at' => $now, 'access_revoked_at' => null]);
        }
    }
}
