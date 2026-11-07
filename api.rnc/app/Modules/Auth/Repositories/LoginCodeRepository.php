<?php

namespace App\Modules\Auth\Repositories;

use App\Modules\Auth\Models\LoginCode;

class LoginCodeRepository
{
    public function __construct(
        private LoginCode $model
    ) {}

    public function createLoginCode(array $data): LoginCode
    {
        return $this->model->create($data);
    }

    /**
     * The code a verify attempt is checked against: the newest row for the phone that nothing has
     * consumed, locked for the rest of the transaction so two submits cannot both spend it.
     * Expiry is the service's call, not a filter here — an expired code answers "expired", not
     * "invalid".
     */
    public function findLatestUnconsumedLoginCodeByPhoneForUpdate(string $phone): ?LoginCode
    {
        return $this->model
            ->newQuery()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Whether the newest code for the phone was consumed by running out of guesses — not used, not
     * superseded (a superseded code never reaches the limit, and a used one stops counting).
     */
    public function wasLatestLoginCodeBurned(string $phone, int $maxAttempts): bool
    {
        $latest = $this->model->newQuery()->where('phone', $phone)->latest('id')->first();

        return $latest !== null && $latest->consumed_at !== null && $latest->attempts >= $maxAttempts;
    }

    /**
     * Supersede every open code for the phone — only the most recently sent code may sign in.
     */
    public function consumeOpenLoginCodesForPhone(string $phone): int
    {
        return $this->model
            ->newQuery()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    public function updateLoginCode(LoginCode $loginCode, array $data): LoginCode
    {
        $loginCode->fill($data)->save();

        return $loginCode;
    }
}
