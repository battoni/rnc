<?php

namespace App\Modules\Firm\Services;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\UserService;
use App\Modules\Firm\DTOs\OffboardedFirm;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Lead\Services\LeadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A firm leaving the platform (rnc-4 R40, A49; `firm:offboard`). In this order:
 *
 * 1. the firm's data goes to a JSON file on the private `local` disk (`offboarding/<slug>-<time>.json`):
 *    the firm's contact, its logins (no password) and every lead as `LeadService::exportData` gives
 *    it — handed to the firm, then the operator deletes the file;
 * 2. every lead is anonymised by `LeadService` (A48's rules, a timeline line `saida_do_escritorio`), and
 *    every login of the firm by Auth's `UserService::anonymiseFirmUsers` (sessions ended, verify P1-A2);
 * 3. the firm is closed: `offboarded_at` written, so its host is 404 (`FirmResolver`), nobody belongs
 *    to it any more and an open session is refused (`ResolveCurrentFirm`). The row stays.
 *
 * Steps 2 and 3 are one transaction: a failure leaves the firm open with its leads as they were, and
 * the export file is deleted (verify P2-A5) and can be written again. The platform firm, an unknown slug and a firm already closed
 * are refused before anything is written.
 */
class FirmOffboarder
{
    public const DIRECTORY = 'offboarding';

    public const NOT_FOUND = 'firms.notFound';

    public const PLATFORM = 'firms.offboard.platform';

    public const ALREADY = 'firms.offboard.already';

    private const DISK = 'local';

    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmRepository $firmRepository,
        private LeadRepository $leadRepository,
        private LeadService $leadService,
        private UserRepository $userRepository,
        private UserService $userService,
    ) {}

    /** The firm the slug names, refused when it may not be offboarded. */
    public function findOffboardable(string $slug): Firm
    {
        $firm = $this->firmRepository->findFirmBySlug($slug);

        if (! $firm) {
            throw ValidationException::withMessages(['slug' => [self::NOT_FOUND]]);
        }

        if ($firm->is_platform) {
            throw ValidationException::withMessages(['slug' => [self::PLATFORM]]);
        }

        if ($firm->isOffboarded()) {
            throw ValidationException::withMessages(['slug' => [self::ALREADY]]);
        }

        return $firm;
    }

    public function offboard(string $slug): OffboardedFirm
    {
        $firm = $this->findOffboardable($slug);
        $now = Carbon::now();

        $path = self::DIRECTORY.'/'.$firm->slug.'-'.$now->format('Ymd-His').'.json';
        Storage::disk(self::DISK)->put($path, (string) json_encode($this->currentFirm->runAs($firm, fn () => $this->export($firm, $now)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $anonymised = DB::transaction(function () use ($firm, $now) {
                $count = 0;

                $this->currentFirm->runAs($firm, function () use (&$count) {
                    $this->leadRepository->eachLeadOfCurrentFirm(function (Lead $lead) use (&$count) {
                        $this->leadService->anonymise($lead, null, LeadService::ANONYMISED_VIA_OFFBOARDING);
                        $count++;
                    });
                });

                $this->userService->anonymiseFirmUsers($firm->id);
                $this->firmRepository->updateFirm($firm, ['offboarded_at' => $now]);

                return $count;
            });
        } catch (Throwable $exception) {
            // The firm stays open with its data as it was: a file of personal data nobody was told
            // about must not outlive the failed exit (verify P2-A5). The next run writes it again.
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }

        return new OffboardedFirm(firm: $firm, path: $path, leads: $anonymised);
    }

    /** @return array<string, mixed> */
    private function export(Firm $firm, Carbon $now): array
    {
        $leads = [];
        $this->leadRepository->eachLeadOfCurrentFirm(function (Lead $lead) use (&$leads) {
            $leads[] = $this->leadService->exportData($lead);
        });

        return [
            'exported_at' => $now->toIso8601String(),
            'firm' => [
                'name' => $firm->name,
                'slug' => $firm->slug,
                'address' => $firm->address,
                'phone' => $firm->phone,
                'mailbox' => $firm->mailbox,
                'modules' => $firm->modules,
            ],
            'users' => array_map(fn (User $user) => [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role instanceof Role ? $user->role->slug : null,
                'status' => $user->status?->value,
                'is_specialist' => (bool) $user->is_specialist,
            ], $this->userRepository->findAllFirmUsers($firm->id)),
            'leads' => $leads,
        ];
    }
}
