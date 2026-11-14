<?php

use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;

/*
 * R38 (rnc-4 F9a, the API half of A47): `GET /api/public/privacy` — the data the privacy notice
 * (`/privacidade`, F9b) fills its template with, per firm: the firm as the data controller, the
 * platform as the operator that processes on its behalf, what is collected, how long it is kept, and
 * the firm's contact for a data request. An allow-list: no id, no timestamps, nothing of another firm.
 */
beforeEach(function () {
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG, 'platform.name' => 'Hub da Reforma']);
    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class]);
});

describe('GET /api/public/privacy', function () {
    it("answers the host firm as the controller, the platform as the operator, and the firm's contact", function () {
        $alvorada = Firm::factory()->create([
            'slug' => 'alvorada',
            'name' => 'Alvorada Contabilidade',
            'address' => 'Rua das Acácias, 120, Campinas-SP',
            'phone' => '19 3333-4444',
            'mailbox' => 'privacidade@alvorada.exemplo',
            'modules' => ['partners'],
        ]);

        $data = $this->withHeader('X-Firm', $alvorada->slug)->getJson('/api/public/privacy')->assertOk()->json('data');

        expect($data['controller'])->toBe([
            'name' => 'Alvorada Contabilidade',
            'address' => 'Rua das Acácias, 120, Campinas-SP',
            'is_platform' => false,
        ])
            ->and($data['operator'])->toBe(['name' => 'Hub da Reforma'])
            ->and($data['requests'])->toBe(['email' => 'privacidade@alvorada.exemplo', 'phone' => '19 3333-4444'])
            ->and($data['collected'])->toContain('contact_name', 'email', 'phone', 'cnpj', 'company', 'diagnostic_answers', 'consent_records')
            ->and(array_column($data['retention'], 'data'))->toBe(['lead_data', 'consent_records', 'site_visits'])
            ->and($data['sharing'])->toBe(['partner_network'])
            ->and($data)->not->toHaveKey('id');
    });

    it('lists no sharing when the firm has the partner network off', function () {
        Firm::factory()->create(['slug' => 'alvorada', 'modules' => []]);

        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/privacy')->assertOk()->assertJsonPath('data.sharing', []);
    });

    it("never answers another firm's contact", function () {
        Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade', 'mailbox' => 'privacidade@alvorada.exemplo']);

        $body = $this->withHeader('X-Firm', FirmSeeder::FRANCO_SLUG)->getJson('/api/public/privacy')->assertOk()->getContent();

        expect($body)->not->toContain('alvorada');
    });

    it('answers the platform firm itself at the root', function () {
        $this->getJson('/api/public/privacy')->assertOk()->assertJsonPath('data.controller.is_platform', true);
    });

    it('answers 404 for an unknown firm', function () {
        $this->withHeader('X-Firm', 'nao-existe')->getJson('/api/public/privacy')->assertNotFound();
    });
});
