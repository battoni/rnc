<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The answer to `POST /api/auth/phone/code`. `dev_code` exists only while
 * `auth.login_code.expose` is on outside production — otherwise the key is absent, never null.
 */
class LoginCodeRequestedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            'phone' => $result['phone'],
            'expires_in' => $result['expires_in'],
            'dev_code' => $this->when($result['dev_code'] !== null, $result['dev_code']),
        ];
    }
}
