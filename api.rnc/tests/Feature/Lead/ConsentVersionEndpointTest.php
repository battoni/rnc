<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\FirmConsentVersion;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A38 (rnc-4 R33; plan F5a): the firm's manager (`firm.settings`) reads its consent's versions —
 * version, words, date, author — and writes a new text as version n+1 with itself as the author.
 * Nothing is ever edited in place: an answer already stored keeps its version and its words, and a
 * new diagnostic stores the new one. The words must name the firm through `{escritorio}` (as the
 * platform default, C15). The team is 403; another firm's versions are never listed.
 *
 *   GET  /api/firm/consent-versions   newest first
 *   POST /api/firm/consent-versions   {text} → v n+1
 */
const ENDPOINT_V1 = 'Autorizo a Franco Contabilidade a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

const ENDPOINT_V2 = 'Autorizo {escritorio} a me apresentar a especialistas parceiros quando o diagnóstico indicar.';

function endpointDiagnostic(string $visitor): string
{
    $token = test()->postJson('/api/public/diagnostics', ['visitor' => $visitor, 'path' => 'quiz', 'source' => 'direto'])
        ->assertCreated()
        ->json('data.token');

    test()->patchJson("/api/public/diagnostics/{$token}", [
        'contact' => ['company' => 'Clínica Horizonte', 'name' => 'Ana Souza', 'phone' => '(11) 98888-7777', 'email' => 'ana@horizonte.com.br', 'consent' => true],
        'step' => 'perfil',
    ])->assertOk();

    return $token;
}

function endpointConsentOf(string $token): LeadConsent
{
    $lead = Lead::withoutGlobalScopes()->where('token', $token)->sole();

    return LeadConsent::query()->where('lead_id', $lead->id)->sole();
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);
    FirmConsentVersion::withoutGlobalScopes()->create(['firm_id' => $this->alvorada->id, 'version' => 1, 'text' => 'Autorizo a {escritorio} — palavras da Alvorada.']);
    $this->manager = assistantPerson('gestao', attributes: ['name' => 'Helena', 'last_name' => 'Gestora']);
    $this->team = assistantPerson('equipe');
});

describe('GET /api/firm/consent-versions', function () {
    it("lists the firm's versions newest first, with words, date and author", function () {
        $this->actingAs($this->manager)->postJson('/api/firm/consent-versions', ['text' => ENDPOINT_V2])->assertCreated();

        $versions = $this->actingAs($this->manager)->getJson('/api/firm/consent-versions')->assertOk()->json('data');

        expect(array_column($versions, 'version'))->toBe([2, 1])
            ->and($versions[0]['text'])->toBe(ENDPOINT_V2)
            ->and($versions[0]['author'])->toBe(['id' => $this->manager->id, 'name' => 'Helena Gestora'])
            ->and($versions[0]['created_at'])->not->toBeNull()
            ->and($versions[1]['author'])->toBeNull()
            ->and(array_keys($versions[0]))->toBe(['version', 'text', 'created_at', 'author']);
    });

    it("never lists another firm's versions", function () {
        $texts = array_column($this->actingAs($this->manager)->getJson('/api/firm/consent-versions')->assertOk()->json('data'), 'text');

        expect($texts)->not->toContain('Autorizo a {escritorio} — palavras da Alvorada.');
    });

    it('refuses the team', function () {
        $this->actingAs($this->team)->getJson('/api/firm/consent-versions')->assertForbidden();
    });
});

describe('POST /api/firm/consent-versions (A38)', function () {
    it('creates version n+1 with its author, and only in its firm', function () {
        $this->actingAs($this->manager)->postJson('/api/firm/consent-versions', ['text' => '  '.ENDPOINT_V2.'  '])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.text', ENDPOINT_V2)
            ->assertJsonPath('data.author.id', $this->manager->id);

        $stored = FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->where('version', 2)->sole();

        expect($stored->author_user_id)->toBe($this->manager->id)
            ->and(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->alvorada->id)->count())->toBe(1);
    });

    it('keeps a stored answer on its version and words, and a new diagnostic stores the new one', function () {
        $old = endpointDiagnostic('5c1f7a2e-3b4d-4e6f-8a9b-0c1d2e3f4a5b');

        $this->actingAs($this->manager)->postJson('/api/firm/consent-versions', ['text' => ENDPOINT_V2])->assertCreated();
        $this->app['auth']->forgetGuards();

        $new = endpointDiagnostic('f1e2d3c4-b5a6-4789-8abc-def012345678');

        expect(endpointConsentOf($old)->only(['version', 'text']))->toBe(['version' => 1, 'text' => ENDPOINT_V1])
            ->and(endpointConsentOf($new)->only(['version', 'text']))->toBe([
                'version' => 2,
                'text' => 'Autorizo Franco Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar.',
            ])
            ->and(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->where('version', 1)->value('text'))
            ->toBe(FirmSeeder::CONSENT_V1);
    });

    it('refuses words that do not name the firm, empty words and words too long', function (string $text) {
        $this->actingAs($this->manager)->postJson('/api/firm/consent-versions', ['text' => $text])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');

        expect(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe(1);
    })->with([
        'no placeholder' => ['Autorizo o escritório a me apresentar a especialistas.'],
        'empty' => ['   '],
        'too long' => [str_repeat('a', 990).' {escritorio}'],
    ]);

    it('refuses the team, and writes nothing', function () {
        $this->actingAs($this->team)->postJson('/api/firm/consent-versions', ['text' => ENDPOINT_V2])->assertForbidden();

        expect(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe(1);
    });
});
