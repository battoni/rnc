<?php

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Data;

/*
 * A DTO carries what the FormRequest VALIDATED, and nothing else.
 *
 * Found in chapaja's review (2026-09): `XxxDTO::from($request)` normalized a FormRequest through
 * `ArrayableNormalizer`, which is `$request->all()`. A field with no rule, or one an `exclude_*`
 * rule had just removed, still reached the service — while the architecture rule promised
 * "map validated input". `config/data.php` now puts `FormRequestNormalizer` first; these tests
 * go red the moment it is removed or moved after `ArrayableNormalizer`.
 */

class DtoProbeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'nickname' => ['exclude_if:anonymous,true', 'nullable', 'string'],
            'anonymous' => ['sometimes', 'boolean'],
        ];
    }
}

class DtoProbeData extends Data
{
    public function __construct(
        public string $name,
        public ?string $nickname = null,
        public ?string $role = null,
    ) {}
}

beforeEach(function () {
    Route::post('/_dto-probe', fn (DtoProbeRequest $request) => response()->json(DtoProbeData::from($request)->toArray()));
});

describe('a DTO built from a FormRequest', function () {
    it('never receives a field the request does not validate', function () {
        $this->postJson('/_dto-probe', ['name' => 'Ana', 'role' => 'administrator'])
            ->assertSuccessful()
            ->assertJsonPath('name', 'Ana')
            ->assertJsonPath('role', null);
    });

    it('never receives a field an exclude rule removed', function () {
        $this->postJson('/_dto-probe', ['name' => 'Ana', 'nickname' => 'aninha', 'anonymous' => true])
            ->assertSuccessful()
            ->assertJsonPath('nickname', null);
    });

    it('still receives every validated field', function () {
        $this->postJson('/_dto-probe', ['name' => 'Ana', 'nickname' => 'aninha'])
            ->assertSuccessful()
            ->assertJsonPath('name', 'Ana')
            ->assertJsonPath('nickname', 'aninha');
    });
});
