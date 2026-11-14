<?php

namespace App\Modules\Firm\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\UserStoreDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Auth\Services\UserService;
use App\Modules\Booking\Services\SpecialistService;
use App\Modules\Content\Services\SectorPageService;
use App\Modules\Firm\DTOs\CreateFirmDTO;
use App\Modules\Firm\DTOs\ProvisionedFirm;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Makes a firm ready to sign in to (rnc-4 R21, D17) — `firm:create` today, the platform console
 * (F4a) next. In one transaction, so a refusal half-way leaves nothing behind:
 *
 * - the firm, on the default palette (`#1f4e79` / `#f2a900`) and weekday hours, its name as logo
 *   text and short name, the manager's e-mail as its mailbox;
 * - core + `briefing`, written through `FirmModulesService` (the only writer of `firms.modules`);
 * - the six default sector pages (`SectorPageService::startFirm`);
 * - consent version 1 copied from the platform default (`ConsentTextService::startFirm`, R13);
 * - its manager (`gestao`) with a 16-character password returned once, bookable on the firm's
 *   hours only when asked (`--specialist`).
 *
 * Refused (422, every reason at once): a malformed slug (`firms.slugInvalid`), a reserved one
 * (`firms.slugReserved`, `config/platform.php`), a taken one (`firms.slugTaken`); no name; no
 * manager, or one taken; a malformed or taken e-mail. Usernames and e-mails are global (AM2).
 */
class FirmProvisioner
{
    public const SLUG_INVALID = 'firms.slugInvalid';

    public const SLUG_RESERVED = 'firms.slugReserved';

    public const SLUG_TAKEN = 'firms.slugTaken';

    public const NAME_REQUIRED = 'firms.nameRequired';

    public const MANAGER_REQUIRED = 'users.form.usernameRequired';

    public const MANAGER_TAKEN = 'users.form.usernameTaken';

    public const EMAIL_INVALID = 'users.form.emailInvalid';

    public const EMAIL_TAKEN = 'users.form.emailTaken';

    /** A DNS label within `firms.slug`'s 60: lowercase letters, digits and inner hyphens. */
    private const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/';

    private const PRIMARY = '#1f4e79';

    private const ACCENT = '#f2a900';

    private const SHORT_NAME_LENGTH = 40;

    private const PASSWORD_LENGTH = 16;

    private const BUSINESS_HOURS = [
        ['weekdays' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00'],
    ];

    /** @var list<string> */
    private const MODULES = [FirmModules::BRIEFING];

    public function __construct(
        private FirmRepository $firmRepository,
        private RoleRepository $roleRepository,
        private CurrentFirm $currentFirm,
        private FirmModulesService $firmModulesService,
        private UserService $userService,
        private SectorPageService $sectorPageService,
        private ConsentTextService $consentTextService,
        private SpecialistService $specialistService,
    ) {}

    /** `$actor` is who asks (the console's operator); a command line has none and acts as the new manager. */
    public function provision(CreateFirmDTO $dto, ?User $actor = null): ProvisionedFirm
    {
        $this->refuseWhatCannotBe($dto);

        $password = Str::password(self::PASSWORD_LENGTH, symbols: false);

        return DB::transaction(function () use ($dto, $actor, $password): ProvisionedFirm {
            $firm = $this->firmRepository->createFirm([
                'slug' => $dto->slug,
                'name' => $dto->name,
                'logo_text' => $dto->name,
                'short_name' => mb_substr($dto->name, 0, self::SHORT_NAME_LENGTH),
                'brand_primary' => self::PRIMARY,
                'brand_accent' => self::ACCENT,
                'mailbox' => $dto->email,
                'business_hours' => self::BUSINESS_HOURS,
            ]);

            $manager = $this->currentFirm->runAs($firm, function () use ($firm, $dto, $password): User {
                $this->sectorPageService->startFirm($firm);
                $this->consentTextService->startFirm($firm->id);

                $manager = $this->createManager($dto, $password);

                if ($dto->specialist) {
                    $this->specialistService->makeBookable($manager, $firm);
                }

                return $manager;
            });

            $firm = $this->firmModulesService->set($firm, self::MODULES, $actor ?? $manager);

            return new ProvisionedFirm($firm, $manager, $password);
        });
    }

    private function createManager(CreateFirmDTO $dto, string $password): User
    {
        [$name, $lastName] = self::namesOf($dto->manager);

        return $this->userService->create(new UserStoreDTO(
            name: $name,
            last_name: $lastName,
            username: $dto->manager,
            role_id: (int) $this->roleRepository->findRoleBySlug(RoleService::MANAGER_SLUG)?->id,
            status: UserStatus::ACTIVE->value,
            password: $password,
            email: $dto->email,
            is_specialist: $dto->specialist,
        ));
    }

    private function refuseWhatCannotBe(CreateFirmDTO $dto): void
    {
        $errors = [];

        if (preg_match(self::SLUG_PATTERN, $dto->slug) !== 1) {
            $errors['slug'] = [self::SLUG_INVALID];
        } elseif (in_array($dto->slug, (array) config('platform.reserved_slugs'), true)) {
            $errors['slug'] = [self::SLUG_RESERVED];
        } elseif ($this->firmRepository->findFirmBySlug($dto->slug) instanceof Firm) {
            $errors['slug'] = [self::SLUG_TAKEN];
        }

        if (trim($dto->name) === '') {
            $errors['name'] = [self::NAME_REQUIRED];
        }

        if (trim($dto->manager) === '') {
            $errors['manager'] = [self::MANAGER_REQUIRED];
        } elseif (! $this->userService->isUsernameAvailable($dto->manager)) {
            $errors['manager'] = [self::MANAGER_TAKEN];
        }

        if ($dto->email !== null && filter_var($dto->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = [self::EMAIL_INVALID];
        } elseif ($dto->email !== null && ! $this->userService->isEmailAvailable($dto->email)) {
            $errors['email'] = [self::EMAIL_TAKEN];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The manager's first and last name from the username until they edit their profile:
     * `helena.acme` → Helena / Acme, `helena` → Helena / "".
     *
     * @return array{string, string}
     */
    private static function namesOf(string $username): array
    {
        $parts = explode('.', $username, 2);

        return [Str::ucfirst($parts[0]), Str::ucfirst($parts[1] ?? '')];
    }
}
