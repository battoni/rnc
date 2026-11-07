<?php

namespace App\Modules\Auth\Repositories;

use App\Modules\Auth\Models\PendingRegistration;
use Carbon\CarbonInterface;

class PendingRegistrationRepository
{
    public function __construct(
        private PendingRegistration $model
    ) {}

    public function findPendingRegistrationByEmail(string $email): ?PendingRegistration
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    /**
     * A non-expired row, locked for the current transaction so concurrent verifies
     * serialize — see EmailVerificationService::verify.
     */
    public function findValidPendingRegistrationForUpdate(string $email): ?PendingRegistration
    {
        return $this->model
            ->newQuery()
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();
    }

    /**
     * Prune expired rows so an abandoned attempt does not hold someone's e-mail hostage.
     * Called on each new registration rather than on a schedule: the bootstrap ships no
     * scheduler, and the only moment the row is in the way is when someone tries again.
     */
    public function deleteExpiredPendingRegistrations(): int
    {
        return $this->model->newQuery()->where('expires_at', '<=', now())->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertPendingRegistrationByEmail(array $data): PendingRegistration
    {
        $existing = $this->findPendingRegistrationByEmail($data['email']);

        if (! $existing) {
            return $this->model->create($data);
        }

        $existing->fill($data);
        $existing->save();

        return $existing->refresh();
    }

    public function updatePendingRegistrationCode(PendingRegistration $pending, string $codeHash, CarbonInterface $expiresAt): PendingRegistration
    {
        // A new code is a new budget of guesses.
        $pending->forceFill([
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ])->save();

        return $pending->refresh();
    }

    /**
     * Count a wrong guess; the one that reaches the ceiling also expires the row, so the right
     * code stops working and the person has to ask for a new one.
     */
    public function registerFailedPendingRegistrationAttempt(PendingRegistration $pending, int $maxAttempts): PendingRegistration
    {
        $attempts = $pending->attempts + 1;
        $data = ['attempts' => $attempts];

        if ($attempts >= $maxAttempts) {
            $data['expires_at'] = now();
        }

        $pending->forceFill($data)->save();

        return $pending;
    }

    public function deletePendingRegistrationByEmail(string $email): void
    {
        $this->model->newQuery()->where('email', $email)->delete();
    }
}
