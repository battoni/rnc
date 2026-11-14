<?php

use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;

/*
 * R5 (rnc-1 F2): the public site reads the firm's brand and public contact from the API, through
 * an allow-list resource — no id, no timestamps.
 */
describe('GET /api/public/firm', function () {
    it("serves the default firm's brand and public contact", function () {
        $this->seed(FirmSeeder::class);

        $this->getJson('/api/public/firm')
            ->assertOk()
            ->assertExactJson(['data' => [
                'name' => 'Franco Contabilidade',
                'slug' => 'franco',
                'brand' => null,
                'logo_text' => 'Franco Contabilidade',
                'short_name' => 'Franco',
                'tagline' => null,
                'brand_primary' => '#00375a',
                'brand_accent' => '#14c3ff',
                'proof' => [
                    ['value' => '35 anos', 'label' => 'em São Paulo'],
                    ['value' => '500+', 'label' => 'clientes atendidos'],
                    ['value' => 'ISO 9001', 'label' => 'qualidade certificada'],
                    ['value' => 'RNC', 'label' => 'Rede Nacional de Contabilidade'],
                ],
                'logo_url' => null,
                'is_platform' => false,
                'address' => 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
                'phone' => '11 3732-5500',
                'phone_href' => 'tel:+551137325500',
                'mailbox' => 'saf@franco-rnc.com.br',
                'business_hours' => [
                    ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
                    ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
                ],
                'platform' => ['name' => 'Hub da Reforma'],
                'modules' => ['portfolio', 'fiscal', 'radar', 'partners', 'contracts', 'chat', 'assistant', 'briefing', 'brands', 'channel'],
            ]]);
    });

    /*
     * rnc-4 R17: the public site hides what the firm has off — the chat receptionist above all,
     * whose start the API refuses (422 `modules.disabled`). The opt-in keys only, as `auth/me`
     * lists them (W34); the platform firm's root site has none.
     */
    it("answers the firm's opt-in modules, none for the platform firm", function () {
        $this->seed(FirmSeeder::class);
        Firm::where('slug', 'franco')->firstOrFail()->forceFill(['modules' => ['briefing', 'portfolio']])->save();

        $this->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.modules', ['portfolio', 'briefing']);

        Firm::factory()->create(['slug' => 'hub', 'name' => 'Hub da Reforma', 'is_platform' => true, 'modules' => []]);
        config(['firms.default_slug' => 'hub']);

        $this->getJson('/api/public/firm')->assertOk()
            ->assertJsonPath('data.is_platform', true)
            ->assertJsonPath('data.modules', []);
    });

    it('serves the firm the X-Firm header names', function () {
        $this->seed(FirmSeeder::class);
        Firm::factory()->create(['slug' => 'outra', 'name' => 'Outra Contabilidade']);

        $this->withHeader('X-Firm', 'outra')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.name', 'Outra Contabilidade');
    });

    it('answers 404 while no firm exists', function () {
        $this->getJson('/api/public/firm')->assertNotFound();
    });
});
