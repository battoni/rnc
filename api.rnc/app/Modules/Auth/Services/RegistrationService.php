<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\RegisterDTO;
use App\Modules\Auth\Models\PendingRegistration;
use App\Modules\Auth\Repositories\PendingRegistrationRepository;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private PendingRegistrationRepository $pendingRegistrationRepository,
    ) {}

    /**
     * Park the registration and mail a code — or, when the product has e-mail verification
     * switched off, create the user right here and hand it back.
     *
     * Both paths write the same pending row first. That is deliberate: one description of
     * what a registration IS, and one function that turns it into a user, so the two flows
     * cannot drift into disagreeing about what a new account looks like.
     */
    public function register(RegisterDTO $dto): PendingRegistration|User
    {
        $this->pendingRegistrationRepository->deleteExpiredPendingRegistrations();

        $waiting = $this->pendingRegistrationRepository->findPendingRegistrationByEmail($dto->email);

        /*
         * An address already waiting for its code keeps what it is holding, and only hears from us
         * again.
         *
         * A second registration used to overwrite the pending row — name, document, PASSWORD —
         * and mail a fresh code to the address. So a stranger could register somebody else's
         * e-mail with a password of their own, and the code that arrived in the victim's inbox
         * would create an account they believed was theirs and the stranger could sign into.
         * Re-issuing without rewriting closes that: the only thing a second attempt can do is
         * send the owner another copy of their own code.
         *
         * The answer stays the same either way — a 201 with the pending registration — because a
         * different one would say "this address is already registering here" to anyone who asked.
         * The sweep above ran first, so a row that survives is a live one; an abandoned attempt
         * is gone and the address is free again.
         */
        if ($waiting) {
            /*
             * In EITHER mode. With verification off there is normally no live row to find — the
             * row is promoted the moment it is written — but one survives a switch from on to off,
             * and falling through would overwrite it and promote the second attempt's data at once.
             * Only the resend belongs to the verifying mode.
             */
            if (config('registration.verify_email')) {
                $this->emailVerificationService->issueCodeForPending($waiting);
            }

            return $waiting->refresh();
        }

        $pending = $this->pendingRegistrationRepository->upsertPendingRegistrationByEmail([
            'email' => $dto->email,
            'phone' => $dto->phone,
            'first_name' => $dto->firstName,
            'last_name' => $dto->lastName,
            'password' => $dto->password,
            'profile_type' => $dto->profileType,
            'cpf' => $dto->cpf,
            'birth_date' => $dto->birthDate,
            'code_hash' => '',
            'expires_at' => now(),
        ]);

        if (! config('registration.verify_email')) {
            // Promote and clear in one transaction: a failure between the two left an account AND
            // a pending row for the same address, and the next attempt could not tell which won.
            return DB::transaction(function () use ($pending, $dto): User {
                $user = $this->emailVerificationService->promote($pending);

                $this->pendingRegistrationRepository->deletePendingRegistrationByEmail($dto->email);

                return $user;
            });
        }

        $this->emailVerificationService->issueCodeForPending($pending);

        return $pending->refresh();
    }
}
