<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\DTOs\RegisterDTO;
use App\Modules\Auth\DTOs\RequestEmailCodeDTO;
use App\Modules\Auth\DTOs\VerifyEmailDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\RequestEmailCodeRequest;
use App\Modules\Auth\Requests\VerifyEmailRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\EmailVerificationService;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Auth\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private PermissionService $permissionService,
        private RegistrationService $registrationService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registrationService->register(RegisterDTO::from($request));

        // Verification off: the account exists now, so sign them in and answer with the
        // user, exactly as verify() would have. The client tells the two apart by what
        // came back, not by reading config it has no business knowing.
        if ($result instanceof User) {
            return ApiResponse::created(data: $this->signIn($request, $result));
        }

        return ApiResponse::created(data: [
            'name' => trim("{$result->first_name} {$result->last_name}"),
            'email' => $result->email,
        ]);
    }

    public function requestCode(RequestEmailCodeRequest $request): JsonResponse
    {
        $this->emailVerificationService->issueCode(RequestEmailCodeDTO::from($request)->email);

        // Always the same answer, whether or not anything was pending — see
        // EmailVerificationService::issueCode.
        return ApiResponse::success(data: ['sent' => true]);
    }

    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $credentials = VerifyEmailDTO::from($request);
        $user = $this->emailVerificationService->verify($credentials->email, $credentials->code);

        return ApiResponse::success(data: $this->signIn($request, $user));
    }

    private function signIn(RegisterRequest|VerifyEmailRequest $request, User $user): UserResource
    {
        // An account parked for approval (`registration.new_user_status` = inactive) is created
        // but not signed in. Signing it in let it use the whole app, so the approval step held
        // nobody back; the response still carries the account, and its status tells the screen
        // to say "awaiting approval" instead of opening the app.
        $isAwaitingApproval = $user->status === UserStatus::INACTIVE;

        if ($isAwaitingApproval) {
            return new UserResource($user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return new UserResource($user);
    }
}
