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
                'logo_text' => 'Franco Contabilidade',
                'brand_primary' => '#00375a',
                'brand_accent' => '#14c3ff',
                'address' => 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
                'phone' => '11 3732-5500',
                'phone_href' => 'tel:+551137325500',
                'mailbox' => 'saf@franco-rnc.com.br',
                'business_hours' => [
                    ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
                    ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
                ],
            ]]);
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
