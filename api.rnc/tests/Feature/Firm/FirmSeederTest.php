<?php

use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;

/*
 * R1: Franco Contabilidade is firm 1, with the brand and the public contact from its own site
 * (facts.md §1, fetched 05/11/2026). Running the seeder again changes nothing.
 */
describe('the firm seeder', function () {
    it('seeds Franco with its brand, public contact and hours', function () {
        $this->seed(FirmSeeder::class);

        $firm = Firm::where('slug', 'franco')->firstOrFail();

        expect($firm->id)->toBe(1)
            ->and($firm->name)->toBe('Franco Contabilidade')
            ->and($firm->logo_text)->toBe('Franco Contabilidade')
            ->and($firm->brand_primary)->toBe('#00375a')
            ->and($firm->brand_accent)->toBe('#14c3ff')
            ->and($firm->address)->toBe('Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP')
            ->and($firm->phone)->toBe('11 3732-5500')
            ->and($firm->mailbox)->toBe('saf@franco-rnc.com.br')
            ->and($firm->business_hours)->toBe([
                ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
                ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
            ]);
    });

    it('is idempotent', function () {
        $this->seed(FirmSeeder::class);
        $this->seed(FirmSeeder::class);

        expect(Firm::count())->toBe(1);
    });

    it('never names the partner network or its founder', function () {
        $this->seed(FirmSeeder::class);

        $serialized = json_encode(Firm::first()->toArray(), JSON_UNESCAPED_UNICODE);

        expect($serialized)->not->toContain('Willy')->and($serialized)->not->toContain('APW');
    });
});
