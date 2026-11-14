<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Models\FirmConsentVersion;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Services\ConsentTextService;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A10 (rnc-4 R13, D12; plan F2a): the partner-introduction consent is per firm and versioned. A new
 * text is version n+1 and never touches an older one; a diagnostic answer stores the firm's current
 * version and its words, `{escritorio}` filled with the firm's name — or the sub-brand's — and an
 * answer already given keeps the words it was given under. A firm with no version yet starts at 1,
 * copied from the platform default (`platform_settings.consent_default`).
 */
const CONSENT_VISITOR = '5c1f7a2e-3b4d-4e6f-8a9b-0c1d2e3f4a5b';

const CONSENT_BRAND_VISITOR = '7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d';

const FRANCO_V1 = 'Autorizo a Franco Contabilidade a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

const CONSENT_V2 = 'Autorizo {escritorio} a me apresentar a especialistas parceiros quando o diagnóstico indicar, conforme a política de privacidade.';

function consentDiagnostic(?string $brand = null): string
{
    $request = $brand === null ? test() : test()->withHeader('X-Brand', $brand);
    $token = $request->postJson('/api/public/diagnostics', ['visitor' => $brand === null ? CONSENT_VISITOR : CONSENT_BRAND_VISITOR, 'path' => 'quiz', 'source' => 'direto'])
        ->assertCreated()
        ->json('data.token');

    $request = $brand === null ? test() : test()->withHeader('X-Brand', $brand);
    $request->patchJson("/api/public/diagnostics/{$token}", [
        'contact' => ['company' => 'Clínica Horizonte', 'name' => 'Ana Souza', 'phone' => '(11) 98888-7777', 'email' => 'ana@horizonte.com.br', 'consent' => true],
        'step' => 'perfil',
    ])->assertOk();

    return $token;
}

function versionedConsentOf(string $token): LeadConsent
{
    $lead = Lead::withoutGlobalScopes()->where('token', $token)->sole();

    return LeadConsent::query()->where('lead_id', $lead->id)->sole();
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->consents = app(ConsentTextService::class);
});

describe('consent versions (A10)', function () {
    it("seeds Franco at v1 with today's words, its name in the placeholder", function () {
        $token = consentDiagnostic();

        expect(versionedConsentOf($token)->only(['version', 'text']))->toBe(['version' => 1, 'text' => FRANCO_V1]);
    });

    it('stores v2 for a new diagnostic and leaves v1 and the old answer untouched', function () {
        $old = consentDiagnostic();
        $v1 = FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->where('version', 1)->sole();
        $v1Text = $v1->text;

        $v2 = $this->consents->createVersion($this->franco->id, CONSENT_V2, null);
        $new = test()->postJson('/api/public/diagnostics', ['visitor' => 'f1e2d3c4-b5a6-4789-8abc-def012345678', 'path' => 'quiz', 'source' => 'direto'])->assertCreated()->json('data.token');
        test()->patchJson("/api/public/diagnostics/{$new}", [
            'contact' => ['company' => 'Padaria Sol', 'name' => 'Rui Lima', 'phone' => '(11) 97777-6666', 'email' => 'rui@sol.com.br', 'consent' => true],
            'step' => 'perfil',
        ])->assertOk()->assertJsonPath('data.consent.version', 2);

        expect($v2->version)->toBe(2)
            ->and($v1->fresh()->text)->toBe($v1Text)
            ->and(versionedConsentOf($new)->only(['version', 'text']))->toBe(['version' => 2, 'text' => 'Autorizo Franco Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar, conforme a política de privacidade.'])
            ->and(versionedConsentOf($old)->only(['version', 'text']))->toBe(['version' => 1, 'text' => FRANCO_V1]);
    });

    it("names the sub-brand under prime, at the firm's current version", function () {
        $this->consents->createVersion($this->franco->id, CONSENT_V2, null);

        $token = consentDiagnostic('prime');

        expect(versionedConsentOf($token)->version)->toBe(2)
            ->and(versionedConsentOf($token)->text)->toStartWith('Autorizo Franco Prime Contabilidade a me apresentar');
    });

    it('starts a firm with no version at 1, copied from the platform default, its name filled', function () {
        $this->seed(PlatformFirmSeeder::class);
        Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade']);

        $token = test()->withHeader('X-Firm', 'alvorada')->postJson('/api/public/diagnostics', ['visitor' => CONSENT_VISITOR, 'path' => 'quiz', 'source' => 'direto'])->assertCreated()->json('data.token');
        test()->withHeader('X-Firm', 'alvorada')->patchJson("/api/public/diagnostics/{$token}", [
            'contact' => ['company' => 'Clínica Horizonte', 'name' => 'Ana Souza', 'phone' => '(11) 98888-7777', 'email' => 'ana@horizonte.com.br', 'consent' => true],
            'step' => 'perfil',
        ])->assertOk()->assertJsonPath('data.consent.text', 'Autorizo Alvorada Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar.');

        expect(app(PlatformSettings::class)->consentDefault())->toContain('{escritorio}')
            ->and(versionedConsentOf($token)->version)->toBe(1);
    });

    it("numbers each firm's versions on its own", function () {
        $other = Firm::factory()->create();

        $this->consents->createVersion($this->franco->id, CONSENT_V2, null);
        $first = $this->consents->createVersion($other->id, CONSENT_V2, null);

        expect($first->version)->toBe(2)
            ->and(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $other->id)->pluck('version')->all())->toBe([1, 2])
            ->and(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->pluck('version')->all())->toBe([1, 2]);
    });
});

describe('the consent the visitor reads', function () {
    it("shows the brand's words and the current version before the answer", function () {
        $this->consents->createVersion($this->franco->id, CONSENT_V2, null);
        $prime = FirmBrand::withoutGlobalScopes()->where('slug', 'prime')->sole();

        test()->withHeader('X-Brand', 'prime')->postJson('/api/public/diagnostics', ['visitor' => CONSENT_VISITOR, 'path' => 'quiz', 'source' => 'direto'])
            ->assertCreated()
            ->assertJsonPath('data.consent.version', 2)
            ->assertJsonPath('data.consent.text', 'Autorizo '.$prime->name.' a me apresentar a especialistas parceiros quando o diagnóstico indicar, conforme a política de privacidade.');
    });
});
