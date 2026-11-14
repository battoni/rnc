<?php

namespace App\Modules\Firm\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\UserStoreDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Auth\Services\UserService;
use App\Modules\Booking\Services\SpecialistService;
use App\Modules\Content\Services\SectorPageService;
use App\Modules\Firm\DTOs\CreateFirmDTO;
use App\Modules\Firm\DTOs\FirmManagerDTO;
use App\Modules\Firm\DTOs\ManagerLogin;
use App\Modules\Firm\DTOs\ProvisionedFirm;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Makes a firm ready to sign in to (rnc-4 R21, D17) — `firm:create` and the platform console
 * (F4a, which also asks it for a manager login: `provideManager`). In one transaction, so a
 * refusal half-way leaves nothing behind:
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

    private const EMAIL_COLUMN = 'users.email';

    private const EMAIL_UNIQUE_INDEX = 'users_email_unique';

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

        try {
            return $this->createAll($dto, $actor, $password);
        } catch (UniqueConstraintViolationException $exception) {
            // Two requests raced past the checks above: the loser names what the winner took — the
            // slug, else the e-mail when the violated index is the e-mail's, else the username.
            throw ValidationException::withMessages(match (true) {
                $this->firmRepository->findFirmBySlug($dto->slug) instanceof Firm => ['slug' => [self::SLUG_TAKEN]],
                self::violatesEmail($exception) => ['email' => [self::EMAIL_TAKEN]],
                default => ['manager' => [self::MANAGER_TAKEN]],
            });
        }
    }

    /**
     * The console's manager login for a firm (rnc-4 R25): a username of that firm's manager gets a
     * new password (every session it had ends); a free username becomes a new `gestao` account in
     * that firm. Any other account's username — another firm's, or a member of this firm who is not
     * its manager — is refused as taken and left as it was, so the console never moves an account
     * between firms or promotes one. The password is answered once.
     */
    public function provideManager(Firm $firm, FirmManagerDTO $dto): ManagerLogin
    {
        $password = Str::password(self::PASSWORD_LENGTH, symbols: false);
        $existing = $this->userService->findByUsername($dto->username);

        if ($existing instanceof User) {
            if (! self::isManagerOf($existing, $firm)) {
                throw ValidationException::withMessages(['username' => [self::MANAGER_TAKEN]]);
            }

            return new ManagerLogin($this->userService->resetPassword($existing, $password), $password, false);
        }

        $this->refuseEmail($dto->email);

        try {
            $manager = DB::transaction(fn (): User => $this->currentFirm->runAs(
                $firm,
                fn (): User => $this->createManager($dto->username, $dto->email, false, $password),
            ));
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(self::violatesEmail($exception)
                ? ['email' => [self::EMAIL_TAKEN]]
                : ['username' => [self::MANAGER_TAKEN]]);
        }

        return new ManagerLogin($manager, $password, true);
    }

    private function createAll(CreateFirmDTO $dto, ?User $actor, string $password): ProvisionedFirm
    {
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

                $manager = $this->createManager($dto->manager, $dto->email, $dto->specialist, $password);

                if ($dto->specialist) {
                    $this->specialistService->makeBookable($manager, $firm);
                }

                return $manager;
            });

            $firm = $this->firmModulesService->set($firm, self::MODULES, $actor ?? $manager);

            return new ProvisionedFirm($firm, $manager, $password);
        });
    }

    private static function isManagerOf(User $user, Firm $firm): bool
    {
        return $user->firm_id !== null
            && (int) $user->firm_id === $firm->id
            && $user->role instanceof Role
            && $user->role->slug === RoleService::MANAGER_SLUG;
    }

    /**
     * Whether the unique index a race hit is the e-mail's, read from the driver's own message (never
     * the SQL, which lists every column): SQLite names the column (`users.email`), MySQL 8 the index
     * (`users.users_email_unique`), as `RoleService` reads its own (rnc-4 F7b).
     */
    private static function violatesEmail(UniqueConstraintViolationException $exception): bool
    {
        $driverMessage = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        return str_contains($driverMessage, self::EMAIL_COLUMN) || str_contains($driverMessage, self::EMAIL_UNIQUE_INDEX);
    }

    private function createManager(string $username, ?string $email, bool $specialist, string $password): User
    {
        [$name, $lastName] = self::namesOf($username);
        $managerRole = $this->roleRepository->findRoleBySlug(RoleService::MANAGER_SLUG);

        // A database without its roles is a broken deploy, not a firm with a role-less manager.
        if ($managerRole === null) {
            throw new RuntimeException('The `'.RoleService::MANAGER_SLUG.'` role is not seeded: run RoleSeeder before firm:create.');
        }

        return $this->userService->create(new UserStoreDTO(
            name: $name,
            last_name: $lastName,
            username: $username,
            role_id: $managerRole->id,
            status: UserStatus::ACTIVE->value,
            password: $password,
            email: $email,
            is_specialist: $specialist,
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

        $emailError = $this->emailError($dto->email);

        if ($emailError !== null) {
            $errors['email'] = [$emailError];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function refuseEmail(?string $email): void
    {
        $emailError = $this->emailError($email);

        if ($emailError !== null) {
            throw ValidationException::withMessages(['email' => [$emailError]]);
        }
    }

    /** Null when the e-mail is absent, or well formed and nobody's yet (unique across firms, AM2). */
    private function emailError(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return self::EMAIL_INVALID;
        }

        return $this->userService->isEmailAvailable($email) ? null : self::EMAIL_TAKEN;
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
