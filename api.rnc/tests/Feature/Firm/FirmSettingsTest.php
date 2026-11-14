<?php

use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A35 (rnc-4 R30, D25; plan F5a): the firm's manager (`firm.settings`, role `gestao`) edits the
 * firm's contact data — address, phone, mailbox, business hours — and its credentials line (the
 * public footer's, W31), and `GET public/firm` shows them. The look, the name, the slug and the
 * modules are the operator's: sent here they are ignored. The team (`equipe`) gets 403, and a
 * manager only ever writes its own firm.
 */
const FRANCO_CREDENTIALS = 'ISO 9001:2015 · PQEC Sescon-SP · Rede Nacional de Contabilidade';

const SETTINGS_HOURS = [
    ['weekdays' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00'],
    ['weekdays' => [6], 'from' => '09:00', 'to' => '12:00'],
];

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade', 'credentials' => null]);
    $this->manager = assistantPerson('gestao');
    $this->team = assistantPerson('equipe');
    $this->alvoradaManager = assistantPerson('gestao', firm: $this->alvorada);
});

describe('GET /api/firm/settings', function () {
    it("answers the manager's own firm: name and slug to read, contact, hours and credentials to edit", function () {
        $this->actingAs($this->manager)->getJson('/api/firm/settings')
            ->assertOk()
            ->assertJsonPath('data.name', 'Franco Contabilidade')
            ->assertJsonPath('data.slug', 'franco')
            ->assertJsonPath('data.address', $this->franco->address)
            ->assertJsonPath('data.phone', '11 3732-5500')
            ->assertJsonPath('data.mailbox', 'saf@franco-rnc.com.br')
            ->assertJsonPath('data.business_hours', $this->franco->business_hours)
            ->assertJsonPath('data.credentials', FRANCO_CREDENTIALS)
            ->assertJsonMissingPath('data.brand_primary')
            ->assertJsonMissingPath('data.modules')
            ->assertJsonMissingPath('data.partner_money_model');
    });

    it("answers another firm's manager its own firm, never Franco's", function () {
        $this->actingAs($this->alvoradaManager)->withHeader('X-Firm', 'alvorada')->getJson('/api/firm/settings')
            ->assertOk()
            ->assertJsonPath('data.slug', 'alvorada')
            ->assertJsonPath('data.credentials', null);
    });

    it('refuses the team and a guest', function () {
        $this->getJson('/api/firm/settings')->assertUnauthorized();
        $this->actingAs($this->team)->getJson('/api/firm/settings')->assertForbidden();
    });
});

describe('PATCH /api/firm/settings (A35)', function () {
    it('changes address, phone, mailbox, hours and credentials, and the public site shows them', function () {
        $this->actingAs($this->manager)->patchJson('/api/firm/settings', [
            'address' => 'Rua Nova, 10, São Paulo-SP',
            'phone' => '11 4000-1000',
            'mailbox' => 'contato@franco.example',
            'business_hours' => SETTINGS_HOURS,
            'credentials' => 'ISO 9001:2015',
        ])
            ->assertOk()
            ->assertJsonPath('data.address', 'Rua Nova, 10, São Paulo-SP')
            ->assertJsonPath('data.credentials', 'ISO 9001:2015');

        $this->app['auth']->forgetGuards();

        $this->withHeader('X-Firm', 'franco')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.address', 'Rua Nova, 10, São Paulo-SP')
            ->assertJsonPath('data.phone', '11 4000-1000')
            ->assertJsonPath('data.phone_href', 'tel:+551140001000')
            ->assertJsonPath('data.mailbox', 'contato@franco.example')
            ->assertJsonPath('data.business_hours', SETTINGS_HOURS)
            ->assertJsonPath('data.credentials', 'ISO 9001:2015');
    });

    it('ignores the look, the name, the slug, the modules and the money model', function () {
        $before = $this->franco->only(['name', 'slug', 'logo_text', 'short_name', 'brand_primary', 'brand_accent', 'modules', 'partner_money_model', 'is_platform']);

        $this->actingAs($this->manager)->patchJson('/api/firm/settings', [
            'name' => 'Tomada',
            'slug' => 'tomada',
            'logo_text' => 'Tomada',
            'short_name' => 'Tomada',
            'brand_primary' => '#ff0000',
            'brand_accent' => '#00ff00',
            'modules' => [],
            'partner_money_model' => 'mensalidade',
            'is_platform' => true,
            'phone' => '11 4000-2000',
        ])->assertOk();

        $after = $this->franco->fresh();

        expect($after->only(array_keys($before)))->toBe($before)
            ->and($after->phone)->toBe('11 4000-2000');
    });

    it('clears a field sent as null and leaves a field not sent alone', function () {
        $this->actingAs($this->manager)->patchJson('/api/firm/settings', ['credentials' => null])->assertOk();

        $after = $this->franco->fresh();

        expect($after->credentials)->toBeNull()
            ->and($after->mailbox)->toBe('saf@franco-rnc.com.br');
    });

    it('writes only the manager\'s own firm', function () {
        $this->actingAs($this->alvoradaManager)->withHeader('X-Firm', 'alvorada')
            ->patchJson('/api/firm/settings', ['phone' => '19 3000-0000'])
            ->assertOk();

        expect($this->alvorada->fresh()->phone)->toBe('19 3000-0000')
            ->and($this->franco->fresh()->phone)->toBe('11 3732-5500');
    });

    it("refuses another firm's header from a manager", function () {
        $this->actingAs($this->alvoradaManager)->withHeader('X-Firm', 'franco')
            ->patchJson('/api/firm/settings', ['phone' => '19 3000-0000'])
            ->assertForbidden();

        expect($this->franco->fresh()->phone)->toBe('11 3732-5500');
    });

    it('refuses the team, and writes nothing', function () {
        $this->actingAs($this->team)->patchJson('/api/firm/settings', ['phone' => '11 4000-3000'])->assertForbidden();

        expect($this->franco->fresh()->phone)->toBe('11 3732-5500');
    });

    it('refuses hours that do not read as a week', function (array $hours) {
        $this->actingAs($this->manager)->patchJson('/api/firm/settings', ['business_hours' => $hours])
            ->assertUnprocessable();

        expect($this->franco->fresh()->business_hours)->toBe($this->franco->business_hours);
    })->with([
        'a weekday out of range' => [[['weekdays' => [0], 'from' => '09:00', 'to' => '18:00']]],
        'a window with no day' => [[['weekdays' => [], 'from' => '09:00', 'to' => '18:00']]],
        'closing before opening' => [[['weekdays' => [1], 'from' => '18:00', 'to' => '09:00']]],
        'a time that is not HH:MM' => [[['weekdays' => [1], 'from' => '9h', 'to' => '18:00']]],
    ]);

    it('takes an empty list of hours as a closed week', function () {
        $this->actingAs($this->manager)->patchJson('/api/firm/settings', ['business_hours' => []])
            ->assertOk()
            ->assertJsonPath('data.business_hours', []);

        expect($this->franco->fresh()->business_hours)->toBe([]);
    });

    it('refuses a mailbox that is not an e-mail', function () {
        $this->actingAs($this->manager)->patchJson('/api/firm/settings', ['mailbox' => 'nada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mailbox');
    });
});

describe('the credentials line on the public site (W31)', function () {
    it("answers Franco's seeded line and none for a firm without one", function () {
        $this->withHeader('X-Firm', 'franco')->getJson('/api/public/firm')->assertJsonPath('data.credentials', FRANCO_CREDENTIALS);
        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')->assertJsonPath('data.credentials', null);
    });
});
