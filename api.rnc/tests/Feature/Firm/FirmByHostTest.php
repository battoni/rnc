<?php

namespace Tests\Feature\Firm;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Traits\BelongsToFirm;
use App\Modules\Lead\Models\Lead;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * rnc-4 F1a — the firm of a request comes from its host (R1–R3, D2, D3; plan AM1, AM9).
 *
 * A1: a visitor on alvorada's host starts a diagnostic and the lead is alvorada's — Franco's team
 * never lists it, alvorada's manager does. A2: an unknown or reserved subdomain is a calm 404,
 * the platform slug included (it is reachable only header-less, at the root). AM1: a signed-in
 * person with no firm (the platform operator) works in the platform firm, never in no firm.
 */
class HostScopedNote extends Model
{
    use BelongsToFirm;

    protected $table = 'host_scoped_notes';

    protected $guarded = [];
}

function hostPlatformFirm(): Firm
{
    return Firm::factory()->create(['slug' => 'hub', 'name' => 'Hub da Reforma', 'is_platform' => true]);
}

function hostOperator(): User
{
    return User::factory()->create([
        'firm_id' => null,
        'role_id' => Role::where('slug', 'operador')->value('id'),
    ]);
}

/** Every card on the pipeline board, whatever its column. */
function hostBoardCount(User $user): int
{
    $columns = test()->actingAs($user)->getJson('/api/leads')->assertOk()->json('data.columns');

    return (int) array_sum(array_column($columns, 'count'));
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade']);
});

describe('A1 — a visitor on a firm host', function () {
    it("starts a diagnostic in that firm, listed by its manager and never by another firm's team", function () {
        $this->seed(LocalUsersSeeder::class);

        $token = $this->withHeader('X-Firm', 'alvorada')
            ->postJson('/api/public/diagnostics', ['visitor' => '7b0c2f4e-1d2a-4c3b-9e8f-0a1b2c3d4e5f', 'path' => 'quiz', 'source' => 'pago'])
            ->assertCreated()
            ->json('data.token');

        expect(Lead::withoutGlobalScopes()->where('token', $token)->value('firm_id'))->toBe($this->alvorada->id);

        $this->flushHeaders();
        $patricia = User::where('username', 'patricia')->firstOrFail();
        $alvoradaManager = User::factory()->create([
            'firm_id' => $this->alvorada->id,
            'role_id' => Role::where('slug', 'ceo')->value('id'),
        ]);

        expect(hostBoardCount($patricia))->toBe(0)
            ->and(hostBoardCount($alvoradaManager))->toBe(1);
    });
});

describe('A2 — a subdomain that is not a firm', function () {
    it('answers 404 firms.notFound to a slug no firm has', function () {
        $this->withHeader('X-Firm', 'xyz')->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'firms.notFound');
    });

    it('answers 404 firms.notFound to every reserved slug, even one a firm row carries', function (string $slug) {
        Firm::factory()->create(['slug' => $slug]);

        $this->withHeader('X-Firm', $slug)->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'firms.notFound');
    })->with(['www', 'api', 'app', 'admin', 'mail', 'static', 'assets', 'docs', 'status', 'beta', 'demo', 'suporte']);

    it('answers 404 to the platform slug sent as a header: the platform firm lives only at the root (AM9)', function () {
        hostPlatformFirm();

        $this->withHeader('X-Firm', 'hub')->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'firms.notFound');
    });

    it('answers 404 to a platform firm under another slug sent as a header (AM9)', function () {
        Firm::factory()->create(['slug' => 'plataforma', 'is_platform' => true]);

        $this->withHeader('X-Firm', 'plataforma')->getJson('/api/public/firm')->assertNotFound();
    });

    it('serves the platform firm at the root when it is the default (D6)', function () {
        config(['firms.default_slug' => 'hub']);
        hostPlatformFirm();

        $this->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.slug', 'hub')
            ->assertJsonPath('data.name', 'Hub da Reforma');
    });

    it('still serves a real firm the header names', function () {
        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alvorada Contabilidade');
    });
});

describe('AM1 — a signed-in person with no firm', function () {
    beforeEach(function () {
        Schema::create('host_scoped_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });

        Route::middleware('api')->prefix('api/test')->group(function () {
            Route::get('host-notes', fn () => HostScopedNote::orderBy('id')->pluck('title'))->middleware('auth');
            Route::post('host-notes', fn () => HostScopedNote::create(['title' => 'nova']))->middleware('auth');
        });
    });

    it('reads the platform firm on public/firm', function () {
        hostPlatformFirm();

        $this->actingAs(hostOperator())->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.slug', 'hub');
    });

    it("lists only the platform firm's rows and stores the platform firm on a create", function () {
        $hub = hostPlatformFirm();
        HostScopedNote::withoutGlobalScopes()->create(['firm_id' => $hub->id, 'title' => 'da plataforma']);
        HostScopedNote::withoutGlobalScopes()->create(['firm_id' => $this->franco->id, 'title' => 'da Franco']);
        $operator = hostOperator();

        $this->actingAs($operator)->getJson('/api/test/host-notes')
            ->assertOk()
            ->assertExactJson(['da plataforma']);

        $this->actingAs($operator)->postJson('/api/test/host-notes')->assertCreated();

        expect(HostScopedNote::withoutGlobalScopes()->where('title', 'nova')->value('firm_id'))->toBe($hub->id);
    });

    it('is refused with 403 firms.required when there is no platform firm, never left in no firm', function () {
        $this->actingAs(hostOperator())->getJson('/api/test/host-notes')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.required');
    });
});
