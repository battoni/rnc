<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Database\Seeders\FirmSeeder;
use Database\Seeders\SectorPageSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * R7 (rnc-1 F2, plan AM2): every page view, CTA click and diagnostic step reaches
 * `POST /api/public/events` with an anonymous visitor id and the derived source. The endpoint
 * accepts only the fields AM2 lists, never takes a `lead_id` from the client, never stores the raw
 * referrer, and is throttled to 60 a minute per address.
 */
const VISITOR = '6f1c2a4e-8b3d-4c5a-9e7f-1a2b3c4d5e6f';

function eventPayload(array $overrides = []): array
{
    return array_merge([
        'visitor' => VISITOR,
        'type' => 'visit',
        'path' => '/reforma/comercio',
        'sector' => 'comercio',
        'source' => 'pago',
        'medium' => 'cpc',
    ], $overrides);
}

beforeEach(function () {
    $this->seed(FirmSeeder::class);
    $this->seed(SectorPageSeeder::class);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

describe('recording an event', function () {
    it('stores the visitor and the event in the current firm', function () {
        $this->postJson('/api/public/events', eventPayload())->assertCreated();

        $visitor = Visitor::withoutGlobalScopes()->sole();
        $event = TrackingEvent::withoutGlobalScopes()->sole();

        expect($visitor->uuid)->toBe(VISITOR)
            ->and($visitor->firm_id)->toBe($this->franco->id)
            ->and($visitor->source)->toBe('pago')
            ->and($visitor->medium)->toBe('cpc')
            ->and($event->firm_id)->toBe($this->franco->id)
            ->and($event->visitor_id)->toBe($visitor->id)
            ->and($event->type)->toBe('visit')
            ->and($event->path)->toBe('/reforma/comercio')
            ->and($event->sector)->toBe('comercio')
            ->and($event->source)->toBe('pago')
            ->and($event->medium)->toBe('cpc')
            ->and($event->lead_id)->toBeNull()
            ->and($event->at)->not->toBeNull();
    });

    it('keeps one visitor row across its events, with the source it first came from', function () {
        $this->postJson('/api/public/events', eventPayload())->assertCreated();
        $this->postJson('/api/public/events', eventPayload(['type' => 'cta', 'source' => 'direto', 'medium' => null]))->assertCreated();

        expect(Visitor::withoutGlobalScopes()->count())->toBe(1)
            ->and(Visitor::withoutGlobalScopes()->value('source'))->toBe('pago')
            ->and(TrackingEvent::withoutGlobalScopes()->orderBy('id')->pluck('type')->all())->toBe(['visit', 'cta']);
    });

    /*
     * Two first beacons of one visitor in parallel: both read "no visitor", the other request's
     * insert lands between this one's read and its insert. The `creating` hook plays the other
     * request; the unique (firm, uuid) index refuses this insert and the winner's row is reused.
     */
    it('reuses the row of a parallel first beacon instead of failing on the unique index', function () {
        Visitor::creating(function (Visitor $visitor) {
            DB::table('visitors')->insertOrIgnore([
                'firm_id' => $this->franco->id,
                'uuid' => $visitor->uuid,
                'source' => 'organico',
                'medium' => null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/public/events', eventPayload())->assertCreated();

        $visitor = Visitor::withoutGlobalScopes()->sole();

        expect($visitor->source)->toBe('organico')
            ->and(TrackingEvent::withoutGlobalScopes()->sole()->visitor_id)->toBe($visitor->id);
    });

    it('accepts an event without a sector or a medium', function () {
        $this->postJson('/api/public/events', eventPayload(['path' => '/', 'sector' => null, 'medium' => null, 'source' => 'direto']))
            ->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->value('sector'))->toBeNull();
    });

    it('takes every type of the funnel', function (string $type) {
        $this->postJson('/api/public/events', eventPayload(['type' => $type]))->assertCreated();
    })->with(['visit', 'cta', 'diag.start', 'diag.contact', 'diag.result', 'booking']);

    it('reads a JSON body sent as text/plain, the way a beacon sends it', function () {
        $this->call('POST', '/api/public/events', [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ACCEPT' => '*/*'], json_encode(eventPayload()))
            ->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->count())->toBe(1);
    });

    it('answers without echoing anything the visitor sent', function () {
        $this->postJson('/api/public/events', eventPayload())->assertCreated()->assertExactJson([]);
    });
});

describe('what the client cannot decide', function () {
    it('never takes a lead id from the request', function () {
        $this->postJson('/api/public/events', eventPayload(['lead_id' => 42]))->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->value('lead_id'))->toBeNull();
    });

    it('ignores a lead token it does not know', function () {
        $this->postJson('/api/public/events', eventPayload(['lead_token' => Str::random(48)]))->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->value('lead_id'))->toBeNull();
    });

    it('resolves the lead from its token, server side (F3)', function () {
        $token = $this->postJson('/api/public/diagnostics', ['path' => 'quiz', 'source' => 'pago'])->json('data.token');

        $this->postJson('/api/public/events', eventPayload(['type' => 'diag.contact', 'lead_token' => $token]))->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->value('lead_id'))->toBe(Lead::withoutGlobalScopes()->sole()->id);
    });

    it('ignores an expired lead token and one of another firm', function () {
        Firm::factory()->create(['slug' => 'outra']);
        $expired = $this->postJson('/api/public/diagnostics', ['path' => 'quiz', 'source' => 'pago'])->json('data.token');
        $foreign = $this->withHeader('X-Firm', 'outra')->postJson('/api/public/diagnostics', ['path' => 'quiz', 'source' => 'pago'])->json('data.token');
        $this->travel(31)->days();

        $this->withHeader('X-Firm', 'franco')->postJson('/api/public/events', eventPayload(['lead_token' => $expired]))->assertCreated();
        $this->travelBack();
        $this->withHeader('X-Firm', 'franco')->postJson('/api/public/events', eventPayload(['lead_token' => $foreign]))->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->pluck('lead_id')->all())->toBe([null, null]);
    });

    it('does not store the raw referrer', function () {
        $this->postJson('/api/public/events', eventPayload(['referrer' => 'https://www.google.com/search?q=franco']))->assertCreated();

        $stored = json_encode([
            TrackingEvent::withoutGlobalScopes()->sole()->toArray(),
            Visitor::withoutGlobalScopes()->sole()->toArray(),
        ]);

        expect($stored)->not->toContain('google');
    });

    it('records the event in the firm of the request, not one named in the body', function () {
        $other = Firm::factory()->create(['slug' => 'outra']);

        $this->postJson('/api/public/events', eventPayload(['firm_id' => $other->id, 'sector' => null]))->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->value('firm_id'))->toBe($this->franco->id);
    });
});

describe('validation', function () {
    it('refuses a payload outside the contract', function (array $overrides, string $field) {
        $this->postJson('/api/public/events', eventPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(TrackingEvent::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'no visitor' => [['visitor' => null], 'visitor'],
        'a visitor that is not a uuid' => [['visitor' => 'visitante-1'], 'visitor'],
        'a uuid that is not v4' => [['visitor' => 'a8098c1a-f86e-11da-bd1a-00112444be1e'], 'visitor'],
        'an unknown type' => [['type' => 'purchase'], 'type'],
        'no path' => [['path' => null], 'path'],
        'a path over 255 characters' => [['path' => '/'.str_repeat('a', 255)], 'path'],
        'an unknown sector' => [['sector' => 'mineracao'], 'sector'],
        'an unknown source' => [['source' => 'google'], 'source'],
        'no source' => [['source' => null], 'source'],
        'a medium over 60 characters' => [['medium' => str_repeat('m', 61)], 'medium'],
    ]);

    it("refuses a sector of another firm's pages", function () {
        Firm::factory()->create(['slug' => 'outra']);

        $this->withHeader('X-Firm', 'outra')->postJson('/api/public/events', eventPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sector']);
    });
});

describe('the throttle', function () {
    it('answers 429 after 60 events in a minute from one address', function () {
        foreach (range(1, 60) as $attempt) {
            $this->postJson('/api/public/events', eventPayload())->assertCreated();
        }

        $this->postJson('/api/public/events', eventPayload())->assertTooManyRequests();

        expect(TrackingEvent::withoutGlobalScopes()->count())->toBe(60);
    });
});
