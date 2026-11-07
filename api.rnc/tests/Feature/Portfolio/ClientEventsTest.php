<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Portfolio\Enums\ClientEventType;
use App\Modules\Portfolio\Models\ClientEvent;
use App\Modules\Portfolio\Services\ClientService;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Support\FiscalRows;

/*
 * A client's timeline (rnc-2 F7, R20, R22, D12, A17's Pest half):
 *
 *   GET /api/clients/{id}/events   the lines, newest first: {id, type, data, at, user: {id, name}}
 *
 * Owned by Portfolio (`client_events`); `ClientService::recordEvent` is its only writer — Fiscal's
 * "Registrar aviso" and "Propor revisão" go through it. Behind `fiscal.read` (equipe, ceo), the
 * only screens that write it so far.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->equipe = FiscalRows::person('equipe');
    $this->client = FiscalRows::client();
    $this->service = app(ClientService::class);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

describe('recording a line', function () {
    it('stamps the firm, the user, the type, the data and the moment in UTC', function () {
        $event = $this->service->recordEvent($this->client, ClientEventType::NfeNotice, ['invoices' => 3], $this->equipe->id);

        $stored = ClientEvent::withoutGlobalScopes()->sole();
        expect($stored->id)->toBe($event->id)
            ->and($stored->firm_id)->toBe($this->franco->id)
            ->and($stored->client_id)->toBe($this->client->id)
            ->and($stored->user_id)->toBe($this->equipe->id)
            ->and($stored->type)->toBe('nfe.aviso')
            ->and($stored->data)->toBe(['invoices' => 3])
            ->and($stored->at->toIso8601String())->toBe('2026-11-09T16:00:00+00:00');
    });

    it('names the two Phase 2 line types', function () {
        expect(ClientEventType::NfeNotice->value)->toBe('nfe.aviso')
            ->and(ClientEventType::SupplierReview->value)->toBe('fornecedores.revisao');
    });
});

describe('the timeline', function () {
    it('lists the client lines newest first, with who did each one', function () {
        $this->service->recordEvent($this->client, ClientEventType::NfeNotice, ['invoices' => 3], $this->equipe->id);
        CarbonImmutable::setTestNow('2026-11-09 15:30:00');
        $this->service->recordEvent($this->client, ClientEventType::SupplierReview, ['simples_share' => 30.0], null);
        $other = FiscalRows::client();
        $this->service->recordEvent($other, ClientEventType::NfeNotice, [], $this->equipe->id);

        $lines = $this->actingAs($this->equipe)->getJson("/api/clients/{$this->client->id}/events")->assertOk()->json('data');

        expect(array_column($lines, 'type'))->toBe(['fornecedores.revisao', 'nfe.aviso'])
            ->and($lines[0])->toMatchArray(['data' => ['simples_share' => 30], 'at' => '2026-11-09T18:30:00+00:00', 'user' => null])
            ->and($lines[1]['user'])->toBe(['id' => $this->equipe->id, 'name' => trim($this->equipe->name.' '.$this->equipe->last_name)]);
    });

    it('answers a client without lines with an empty list', function () {
        $this->actingAs($this->equipe)->getJson("/api/clients/{$this->client->id}/events")->assertOk()->assertJsonPath('data', []);
    });

    it('answers another firm client and an unknown id 404', function () {
        $otherFirm = Firm::factory()->create();
        $theirs = FiscalRows::client([], $otherFirm->id);
        app(CurrentFirm::class)->runAs($otherFirm, fn () => $this->service->recordEvent($theirs, ClientEventType::NfeNotice, [], null));

        $this->actingAs($this->equipe)->getJson("/api/clients/{$theirs->id}/events")->assertNotFound();
        $this->getJson('/api/clients/999999/events')->assertNotFound();
    });

    it('answers a guest 401 and a partner login 403', function () {
        $this->getJson("/api/clients/{$this->client->id}/events")->assertUnauthorized();
        $this->actingAs(FiscalRows::person('parceiro'))->getJson("/api/clients/{$this->client->id}/events")->assertForbidden();
    });
});
