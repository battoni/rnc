<?php

namespace App\Modules\Auth\Requests;

use App\Helpers\Brazil\Cpf;
use App\Helpers\Brazil\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            /*
             * The bootstrap asks for a document and a date of birth because every product in
             * this family so far needs both. The check digits are verified here and the
             * uniqueness below sees the same digits-only form the row stores. The CPF rule is
             * Brazil's, so it comes from `App\Helpers\Brazil` — a product outside Brazil
             * replaces this field and that directory, and nothing else in the flow knows.
             */
            'cpf' => [
                'required',
                'string',
                'max:20',
                'unique:users,cpf',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Cpf::isValid(is_string($value) ? $value : null)) {
                        $fail('auth.flow.signUp.invalidCpf');
                    }
                },
            ],
            'birthDate' => ['required', 'date', 'before:today'],
            'termsAccepted' => ['accepted'],
            /*
             * Required only when there is something to choose. With a single profile
             * configured the screen asks nothing, so demanding the field would fail a form
             * the person filled in completely — but the allow-list still applies to whatever
             * does arrive, because a screen that shows no choice is not a screen that
             * prevents one being sent.
             */
            'profileType' => [
                $this->profileIsAChoice() ? 'required' : 'nullable',
                Rule::in(array_keys((array) config('registration.profile_types'))),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The screen sends the mask it showed. The database stores digits, or the same
        // person registers twice by typing the dots differently.
        if ($this->filled('cpf')) {
            $this->merge(['cpf' => Cpf::normalize((string) $this->input('cpf'))]);
        }

        // Same reason for the phone: "(11) 99999-8888" and "+55 11 99999-8888" are one number.
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        }

        if ($this->profileIsAChoice() || $this->filled('profileType')) {
            return;
        }

        $this->merge(['profileType' => array_key_first((array) config('registration.profile_types'))]);
    }

    private function profileIsAChoice(): bool
    {
        return count((array) config('registration.profile_types')) > 1;
    }
}
