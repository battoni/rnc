<?php

namespace App\Modules\Firm\Services;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;

/**
 * Which firm an address names, and whether an account may work in it (rnc-4 R1–R4, D2, D3, D5).
 *
 * One place for the two questions every door asks: `ResolveCurrentFirm` for a visitor's `X-Firm`
 * header, the password and phone-code logins and the QA login-as for "does this account belong
 * here". A reserved slug, an unknown one and the platform firm's own all read as "no firm" — the
 * platform firm is reached only header-less, at the root (plan AM9).
 */
class FirmResolver
{
    private const WWW = 'www';

    public function __construct(private FirmRepository $firmRepository) {}

    /** The firm a visitor's `X-Firm` header names, or null for a reserved, unknown, platform or offboarded slug. */
    public function findTenantBySlug(string $slug): ?Firm
    {
        if (in_array($slug, $this->reservedSlugs(), true)) {
            return null;
        }

        $firm = $this->firmRepository->findFirmBySlug($slug);

        return $firm !== null && ! $firm->is_platform && ! $firm->isOffboarded() ? $firm : null;
    }

    /** The firm of a request that names none: the root host's (`DEFAULT_FIRM_SLUG`, D6). */
    public function findDefaultFirm(): ?Firm
    {
        return $this->firmRepository->findFirmBySlug((string) config('firms.default_slug'));
    }

    /**
     * The firm a browser address names: `<slug>.<root>` is that firm (null when the slug is
     * reserved or unknown), the root, `www` and any host outside the root are the default firm.
     */
    public function findFirmByHost(string $host): ?Firm
    {
        $suffix = '.'.config('platform.root_domain');

        if (! str_ends_with($host, $suffix)) {
            return $this->findDefaultFirm();
        }

        $slug = substr($host, 0, -strlen($suffix));

        return $slug === self::WWW ? $this->findDefaultFirm() : $this->findTenantBySlug($slug);
    }

    /**
     * An account belongs to a firm when it is that firm's; the operator, who has no firm, belongs
     * only to the platform firm (R4). Any other account with no firm belongs nowhere, and nobody
     * belongs to an offboarded firm any more (rnc-4 R40).
     */
    public function belongsTo(User $user, ?Firm $firm): bool
    {
        if ($firm === null || $firm->isOffboarded()) {
            return false;
        }

        if ($user->firm_id !== null) {
            return (int) $user->firm_id === $firm->id;
        }

        $role = $user->role;

        return $firm->is_platform && $role instanceof Role && $role->slug === RoleService::ADMINISTRATOR_SLUG;
    }

    /** @return list<string> */
    private function reservedSlugs(): array
    {
        return array_values((array) config('platform.reserved_slugs'));
    }
}
