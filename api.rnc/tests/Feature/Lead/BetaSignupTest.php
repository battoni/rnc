<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Mail\BetaSignupMail;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Services\ConsentTextService;
use App\Modules\Notification\Notifications\BetaSignupNotification;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * A31 (rnc-4 R29, D29; plan F6a): the product is firm zero. At the root (the platform firm `hub`,
 * reached header-less only — AM9), `POST public/beta-signups` creates a `beta` lead on hub's own
 * pipeline — stage `lead`, path `beta`, the contact in its columns and the rest in `profile` — with
 * the consent answered under hub's current version, and rings hub's team (bell + e-mail, hub's
 * brand). Any other firm is a 404, `X-Firm: hub` included; consent is required (422); the 6th call
 * in a minute is 429. `GET public/beta-signups/consent` hands the form the words it asks under.
 */
function betaSignupBody(array $overrides = []): array
{
    return [
        'company' => 'Contábil Horizonte',
        'name' => 'Marta Ribeiro',
        'role' => 'Sócia',
        'email' => 'Marta@Horizonte.com.br',
        'phone' => '(31) 98888-7777',
        'city' => 'Belo Horizonte',
        'uf' => 'mg',
        'clients_band' => 'de_101_a_300',
        'message' => 'Queremos testar com a nossa carteira de comércio.',
        'consent' => true,
        ...$overrides,
    ];
}

function betaLeads()
{
    return Lead::withoutGlobalScopes()->where('channel', 'beta');
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, PlatformFirmSeeder::class]);
    $this->hub = Firm::where('slug', 'hub')->firstOrFail();
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    config(['firms.default_slug' => 'hub']);
});

describe('POST /api/public/beta-signups at the root (A31)', function () {
    it("creates a beta lead on hub's pipeline with the contact in columns and the rest in profile", function () {
        $this->postJson('/api/public/beta-signups', betaSignupBody())
            ->assertCreated()
            ->assertExactJson(['data' => ['company' => 'Contábil Horizonte', 'channel' => 'beta']]);

        $lead = betaLeads()->sole();

        expect($lead->firm_id)->toBe($this->hub->id)
            ->and($lead->stage)->toBe('lead')
            ->and($lead->path)->toBe('beta')
            ->and($lead->channel)->toBe('beta')
            ->and($lead->source)->toBe('direto')
            ->and($lead->company)->toBe('Contábil Horizonte')
            ->and($lead->contact_name)->toBe('Marta Ribeiro')
            ->and($lead->email)->toBe('marta@horizonte.com.br')
            ->and($lead->phone)->toBe('5531988887777')
            ->and($lead->profile)->toMatchArray([
                'role' => 'Sócia',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
                'clients_band' => 'de_101_a_300',
                'message' => 'Queremos testar com a nossa carteira de comércio.',
                'sector' => null,
            ])
            // Born expired, like a portfolio lead: no public diagnostic route ever opens it.
            ->and($lead->token_expires_at->isFuture())->toBeFalse();

        expect(Lead::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe(0);
    });

    it("stores the consent under hub's current version, its name filled", function () {
        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertCreated();

        $current = app(ConsentTextService::class)->currentVersion($this->hub->id);
        $consent = LeadConsent::query()->where('lead_id', betaLeads()->sole()->id)->sole();

        expect($consent->given)->toBeTrue()
            ->and($consent->version)->toBe($current->version)
            ->and($consent->text)->toBe(str_replace('{escritorio}', $this->hub->name, $current->text))
            ->and($consent->text)->not->toContain('{escritorio}')
            ->and($consent->via)->toBe('formulario');
    });

    it("rings hub's team — bell and e-mail under hub's brand — and nobody else", function () {
        Mail::fake();
        $hubManager = assistantPerson('gestao', firm: $this->hub, attributes: ['email' => 'gestao@hub.test']);
        $hubTeam = assistantPerson('equipe', firm: $this->hub, attributes: ['email' => 'equipe@hub.test']);
        $francoTeam = assistantPerson('equipe', firm: $this->franco);
        $operator = User::factory()->create(['firm_id' => null, 'role_id' => Role::where('slug', 'operador')->value('id')]);

        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertCreated();
        $leadId = betaLeads()->sole()->id;

        foreach ([$hubManager, $hubTeam] as $member) {
            expect($member->notifications()->sole()->data)->toBe([
                'kind' => BetaSignupNotification::KIND,
                'lead_id' => $leadId,
                'company' => 'Contábil Horizonte',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
            ]);
        }
        expect($francoTeam->notifications()->count())->toBe(0)
            ->and($operator->notifications()->count())->toBe(0);

        Mail::assertQueued(BetaSignupMail::class, 2);
        Mail::assertQueued(BetaSignupMail::class, fn (BetaSignupMail $mail) => $mail->hasTo('gestao@hub.test')
            && $mail->signup->brand->name === $this->hub->name
            && $mail->signup->leadUrl() === 'http://localhost:5173/equipe/leads/'.$leadId);
        Mail::assertNotQueued(BetaSignupMail::class, fn (BetaSignupMail $mail) => $mail->hasTo($francoTeam->email));
    });

    it("renders the team's e-mail with the signup's answers, in both parts", function () {
        Mail::fake();
        assistantPerson('gestao', firm: $this->hub, attributes: ['email' => 'gestao@hub.test']);

        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertCreated();

        Mail::assertQueued(BetaSignupMail::class, function (BetaSignupMail $mail) {
            $mail->locale('pt_BR');
            $mail->assertSeeInHtml('Contábil Horizonte')
                ->assertSeeInHtml('Marta Ribeiro')
                ->assertSeeInHtml('101 a 300')
                ->assertSeeInHtml('Queremos testar com a nossa carteira de comércio.')
                ->assertSeeInText('Belo Horizonte/MG')
                ->assertSeeInText('marta@horizonte.com.br')
                ->assertSeeInText('/equipe/leads/');

            return $mail->envelope()->from->name === $this->hub->name;
        });
    });

    it("lets hub's team open the beta lead on its pipeline", function () {
        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertCreated();
        $lead = betaLeads()->sole();

        $this->actingAs(assistantPerson('gestao', firm: $this->hub))
            ->getJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.channel', 'beta')
            ->assertJsonPath('data.stage', 'lead');
    });

    it('takes the visit source when the site sends one', function () {
        $this->postJson('/api/public/beta-signups', betaSignupBody(['source' => 'pago', 'medium' => 'linkedin']))->assertCreated();

        expect(betaLeads()->sole())->source->toBe('pago')->medium->toBe('linkedin');
    });
});

describe('beta signups only on the platform firm (A31)', function () {
    it('is a 404 on a firm host', function () {
        $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/public/beta-signups', betaSignupBody())
            ->assertNotFound();

        expect(betaLeads()->count())->toBe(0);
    });

    it('is a 404 before validation on a firm host', function () {
        $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/public/beta-signups', [])
            ->assertNotFound();
    });

    it('is a 404 when the default firm is a tenant', function () {
        config(['firms.default_slug' => 'franco']);

        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertNotFound();

        expect(betaLeads()->count())->toBe(0);
    });

    it('is a 404 with the platform slug in X-Firm (AM9)', function () {
        $this->withHeader('X-Firm', 'hub')
            ->postJson('/api/public/beta-signups', betaSignupBody())
            ->assertNotFound();

        expect(betaLeads()->count())->toBe(0);
    });
});

describe('beta signup validation (A31)', function () {
    it('requires the consent', function (array $overrides) {
        $body = betaSignupBody($overrides);

        $this->postJson('/api/public/beta-signups', array_filter($body, fn ($value) => $value !== '__absent__'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent']);

        expect(betaLeads()->count())->toBe(0);
    })->with([
        'missing' => [['consent' => '__absent__']],
        'refused' => [['consent' => false]],
    ]);

    it('requires each field the form asks', function (string $field) {
        $body = betaSignupBody();
        unset($body[$field]);

        $this->postJson('/api/public/beta-signups', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with(['company', 'name', 'role', 'email', 'phone', 'city', 'uf', 'clients_band']);

    it('refuses a malformed value', function (string $field, mixed $value) {
        $this->postJson('/api/public/beta-signups', betaSignupBody([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'e-mail' => ['email', 'not-an-email'],
        'phone' => ['phone', '123'],
        'uf' => ['uf', 'XX'],
        'band' => ['clients_band', 'muitos'],
        'source' => ['source', 'tv'],
        'message' => ['message', str_repeat('a', 1001)],
    ]);

    it('takes the message as optional', function () {
        $body = betaSignupBody();
        unset($body['message']);

        $this->postJson('/api/public/beta-signups', $body)->assertCreated();

        expect(betaLeads()->sole()->profile['message'])->toBeNull();
    });
});

describe('beta signup throttle (A31)', function () {
    it('answers 429 on the 6th call in a minute', function () {
        foreach (range(1, 5) as $call) {
            $this->postJson('/api/public/beta-signups', betaSignupBody(['email' => "socio{$call}@horizonte.com.br"]))->assertCreated();
        }

        $this->postJson('/api/public/beta-signups', betaSignupBody())->assertTooManyRequests();

        expect(betaLeads()->count())->toBe(5);
    });
});

describe('GET /api/public/beta-signups/consent', function () {
    it("answers hub's current words, its name filled", function () {
        $current = app(ConsentTextService::class)->currentVersion($this->hub->id);

        $this->getJson('/api/public/beta-signups/consent')
            ->assertOk()
            ->assertExactJson(['data' => [
                'text' => str_replace('{escritorio}', $this->hub->name, $current->text),
                'version' => $current->version,
            ]]);
    });

    it('is a 404 on a firm host', function () {
        $this->withHeader('X-Firm', 'franco')->getJson('/api/public/beta-signups/consent')->assertNotFound();
    });
});
