<?php

use App\Modules\Content\Models\SectorPage;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\FirmSeeder;
use Database\Seeders\SectorPageSeeder;

/*
 * R6 (rnc-1 F2): the six sector pages live in the API, firm-scoped, and are served to the public
 * site through an allow-list resource. Each page carries at least three dated facts with a source.
 */
const SECTOR_SLUGS = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'];

beforeEach(function () {
    $this->seed(FirmSeeder::class);
    $this->seed(SectorPageSeeder::class);
});

describe('GET /api/public/sectors', function () {
    it('lists the six sectors of the default firm in the order the site shows them', function () {
        $response = $this->getJson('/api/public/sectors')->assertOk();

        expect(collect($response->json('data'))->pluck('slug')->all())->toBe(SECTOR_SLUGS)
            ->and($response->json('data.0'))->toBe([
                'slug' => 'comercio',
                'label' => 'Comércio',
                'title' => 'Reforma Tributária para o comércio',
                'lede' => SectorPage::withoutGlobalScopes()->where('slug', 'comercio')->value('lede'),
            ]);
    });

    it('lists nothing of another firm', function () {
        Firm::factory()->create(['slug' => 'outra']);

        $this->withHeader('X-Firm', 'outra')->getJson('/api/public/sectors')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });
});

describe('GET /api/public/sectors/{slug}', function () {
    it('serves a page with its lede, dated facts with a source, and its own SEO', function () {
        $page = $this->getJson('/api/public/sectors/servicos')->assertOk()->json('data');

        expect(array_keys($page))->toBe(['slug', 'label', 'title', 'lede', 'facts', 'seo'])
            ->and($page['title'])->toBe('Reforma Tributária para serviços')
            ->and(count($page['facts']))->toBeGreaterThanOrEqual(3)
            ->and(array_keys($page['seo']))->toBe(['title', 'description'])
            ->and($page['seo']['title'])->not->toBeEmpty();

        foreach ($page['facts'] as $fact) {
            expect(array_keys($fact))->toBe(['date', 'text', 'source'])
                ->and($fact['date'])->toMatch('/\d{4}/')
                ->and($fact['source']['label'])->not->toBeEmpty();
        }
    });

    it('answers 404 to a sector that does not exist', function () {
        $this->getJson('/api/public/sectors/mineracao')->assertNotFound();
    });

    it('answers 404 to a sector page of another firm, as if it did not exist', function () {
        Firm::factory()->create(['slug' => 'outra']);

        $this->withHeader('X-Firm', 'outra')->getJson('/api/public/sectors/comercio')->assertNotFound();
    });
});

describe('the sector page seeder', function () {
    it('seeds the six sectors for Franco, each with at least three dated facts', function () {
        $pages = SectorPage::withoutGlobalScopes()->orderBy('position')->get();

        expect($pages->pluck('slug')->all())->toBe(SECTOR_SLUGS)
            ->and($pages->pluck('firm_id')->unique()->all())->toBe([Firm::where('slug', 'franco')->value('id')]);

        foreach ($pages as $page) {
            expect(count($page->facts))->toBeGreaterThanOrEqual(3);
        }
    });

    it('is idempotent', function () {
        $this->seed(SectorPageSeeder::class);

        expect(SectorPage::withoutGlobalScopes()->count())->toBe(6);
    });

    it('never names the partner network or its founder', function () {
        $serialized = json_encode(SectorPage::withoutGlobalScopes()->get()->toArray(), JSON_UNESCAPED_UNICODE);

        expect($serialized)->not->toContain('Willy')->not->toContain('APW');
    });
});
