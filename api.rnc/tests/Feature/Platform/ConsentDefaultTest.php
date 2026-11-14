<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Models\FirmConsentVersion;
use App\Modules\Lead\Services\ConsentTextService;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * rnc-4 F4a — A28 (R25, R13). The platform's default consent sentence is the operator's to edit:
 * the next firm created copies the new words into its version 1, and every firm that already has
 * versions keeps them as they were. It must name the firm through `{escritorio}`.
 */
beforeEach(function () {
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->operator = User::factory()->create(['firm_id' => null, 'role_id' => Role::where('slug', 'operador')->value('id')]);
});

const CONSENT_DEFAULT_NEW_TEXT = 'Autorizo {escritorio} a me colocar em contato com especialistas parceiros.';

it('answers the current default sentence', function () {
    $this->actingAs($this->operator)->getJson('/api/platform/consent-default')
        ->assertOk()
        ->assertJsonPath('data.text', app(PlatformSettings::class)->consentDefault());
});

it("changes what the next firm copies and leaves existing firms' versions as they were", function () {
    $francoBefore = app(ConsentTextService::class)->startFirm($this->franco->id);
    $francoText = $francoBefore->text;
    $francoCount = FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count();

    $this->actingAs($this->operator)->putJson('/api/platform/consent-default', ['text' => CONSENT_DEFAULT_NEW_TEXT])
        ->assertOk()
        ->assertJsonPath('data.text', CONSENT_DEFAULT_NEW_TEXT);

    $this->actingAs($this->operator)->postJson('/api/platform/firms', ['slug' => 'ipe', 'name' => 'Ipê Contábil', 'manager' => 'gestao.ipe'])->assertCreated();

    $ipe = Firm::where('slug', 'ipe')->firstOrFail();
    $ipeVersions = FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $ipe->id)->get();

    expect($ipeVersions)->toHaveCount(1)
        ->and($ipeVersions->first()->text)->toBe(CONSENT_DEFAULT_NEW_TEXT)
        ->and(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe($francoCount)
        ->and($francoBefore->fresh()->text)->toBe($francoText);
});

it('refuses a sentence that does not name the firm, or an empty one', function (string $text) {
    $before = app(PlatformSettings::class)->consentDefault();

    $this->actingAs($this->operator)->putJson('/api/platform/consent-default', ['text' => $text])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');

    expect(app(PlatformSettings::class)->consentDefault())->toBe($before);
})->with([
    'no placeholder' => ['Autorizo o escritório a me apresentar.'],
    'empty' => [''],
]);

it('names the missing placeholder with its key', function () {
    $this->actingAs($this->operator)->putJson('/api/platform/consent-default', ['text' => 'Autorizo o escritório.'])
        ->assertJsonPath('errors.text.0', 'platform.consentPlaceholderMissing');
});

it('refuses everyone but the operator', function (string $role) {
    $person = User::factory()->create(['firm_id' => $this->franco->id, 'role_id' => Role::where('slug', $role)->value('id')]);

    $this->actingAs($person)->getJson('/api/platform/consent-default')->assertForbidden();
    $this->actingAs($person)->putJson('/api/platform/consent-default', ['text' => CONSENT_DEFAULT_NEW_TEXT])->assertForbidden();

    expect(app(PlatformSettings::class)->consentDefault())->not->toBe(CONSENT_DEFAULT_NEW_TEXT);
})->with(['gestao', 'equipe']);
