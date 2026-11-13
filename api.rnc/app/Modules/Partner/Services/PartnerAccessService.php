<?php

namespace App\Modules\Partner\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\LinkedAccountDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Services\LinkedAccountService;
use App\Modules\Partner\DTOs\CreatePartnerAccessDTO;
use App\Modules\Partner\DTOs\IssuedPartnerAccess;
use App\Modules\Partner\Enums\PartnerAccessEventType;
use App\Modules\Partner\Exceptions\PartnerAccessException;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Repositories\PartnerAccessEventRepository;
use App\Modules\Partner\Repositories\PartnerRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A partner's portal login (rnc-2 R1–R2, D1; plan AM1, AM5, AM9), managed with `partners.manage`.
 *
 * - **Grant**: one `users` row per partner, role `parceiro`, born in the PARTNER's firm (AM1 — a
 *   login without a firm would fall through `ResolveCurrentFirm` to the `X-Firm` header). A
 *   second active login is 409 `partner_access_exists`; a closed one is reopened, so a regrant
 *   keeps the same user. The generated password is answered once.
 * - **Regenerate**: a new password, once; the old sessions end.
 * - **Revoke** (and the partner's deactivation): the login inactive, its sessions deleted, so the
 *   cookie it holds is a 401 on its next request (AM5).
 *
 * Each grant and revoke writes one `partner_access_events` row and the current state on the
 * partner (`access_granted_at` / `access_revoked_at`); the ledger bills months from the rows (AM9).
 * Null for an unknown or another firm's partner — the controller answers 404.
 */
class PartnerAccessService
{
    public const PARTNER_ROLE_SLUG = 'parceiro';

    public function __construct(
        private LinkedAccountService $linkedAccountService,
        private PartnerAccessEventRepository $partnerAccessEventRepository,
        private PartnerRepository $partnerRepository,
    ) {}

    public function grant(int $partnerId, CreatePartnerAccessDTO $access): ?IssuedPartnerAccess
    {
        $partner = $this->partnerRepository->findPartnerById($partnerId);

        if (! $partner) {
            return null;
        }

        if (! $partner->active) {
            throw PartnerAccessException::partnerInactive();
        }

        $existing = $this->linkedAccountService->findPartnerAccount($partner->id);

        if ($existing?->status === UserStatus::ACTIVE) {
            throw PartnerAccessException::exists();
        }

        try {
            return DB::transaction(function () use ($partner, $access, $existing) {
                $opened = $this->linkedAccountService->openAccount(new LinkedAccountDTO(
                    name: $partner->name,
                    username: $access->username,
                    email: $access->email,
                    firmId: $partner->firm_id,
                    partnerId: $partner->id,
                    roleSlug: self::PARTNER_ROLE_SLUG,
                ), $existing);

                $this->record($partner, $opened['user'], PartnerAccessEventType::Granted);

                return new IssuedPartnerAccess($this->withAccount($partner), $opened['password']);
            });
        } catch (UniqueConstraintViolationException) {
            // Two grants raced past the check above and `users.partner_id` is unique: the loser
            // gets the same 409. (The request already validated username and e-mail as unique.)
            throw PartnerAccessException::exists();
        }
    }

    public function regeneratePassword(int $partnerId): ?IssuedPartnerAccess
    {
        $partner = $this->partnerRepository->findPartnerById($partnerId);

        if (! $partner) {
            return null;
        }

        $password = $this->linkedAccountService->resetPassword($this->activeLoginOf($partner));

        return new IssuedPartnerAccess($this->withAccount($partner), $password);
    }

    public function revoke(int $partnerId): ?Partner
    {
        $partner = $this->partnerRepository->findPartnerById($partnerId);

        if (! $partner) {
            return null;
        }

        $login = $this->activeLoginOf($partner);

        DB::transaction(fn () => $this->close($partner, $login));

        return $this->withAccount($partner);
    }

    /** The partner was deactivated (R2): its login goes with it. Nothing happens without one. */
    public function closeForDeactivatedPartner(Partner $partner): void
    {
        $login = $this->linkedAccountService->findPartnerAccount($partner->id);

        if ($login?->status !== UserStatus::ACTIVE) {
            return;
        }

        $this->close($partner, $login);
    }

    private function close(Partner $partner, User $login): void
    {
        $this->linkedAccountService->closeAccount($login);
        $this->record($partner, $login, PartnerAccessEventType::Revoked);
    }

    private function activeLoginOf(Partner $partner): User
    {
        $login = $this->linkedAccountService->findPartnerAccount($partner->id);

        if ($login?->status !== UserStatus::ACTIVE) {
            throw PartnerAccessException::missing();
        }

        return $login;
    }

    private function record(Partner $partner, User $login, PartnerAccessEventType $type): void
    {
        $now = CarbonImmutable::now();

        $this->partnerAccessEventRepository->createPartnerAccessEvent([
            'firm_id' => $partner->firm_id,
            'partner_id' => $partner->id,
            'user_id' => $login->id,
            'type' => $type->value,
            'at' => $now,
        ]);

        $this->partnerRepository->updatePartner($partner, $type === PartnerAccessEventType::Granted
            ? ['access_granted_at' => $now, 'access_revoked_at' => null]
            : ['access_revoked_at' => $now]);
    }

    private function withAccount(Partner $partner): Partner
    {
        return $partner->load('account');
    }
}
