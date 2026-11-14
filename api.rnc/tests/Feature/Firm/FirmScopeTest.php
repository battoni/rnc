<?php

namespace Tests\Feature\Firm;

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * A1 (rnc-1): a person of firm A never reads, lists or updates a row of firm B — the answer is a
 * 404, the same as for a row that does not exist. The business tables arrive in later phases, so
 * the scope is proven here on a table that exists only for this test; every model that uses
 * `BelongsToFirm` gets exactly this behaviour.
 */
class FirmScopedNote extends Model
{
    use BelongsToFirm;

    protected $table = 'firm_scoped_notes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('firm_scoped_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('firm_id')->constrained();
        $table->string('title');
        $table->timestamps();
    });

    Route::middleware('api')->prefix('api/test')->group(function () {
        Route::get('notes', fn () => FirmScopedNote::orderBy('id')->pluck('title'))->middleware('auth');
        Route::get('notes/{id}', fn (int $id) => FirmScopedNote::findOrFail($id))->middleware('auth');
        Route::patch('notes/{id}', function (Request $request, int $id) {
            $note = FirmScopedNote::findOrFail($id);
            $note->update(['title' => $request->string('title')->toString()]);

            return $note;
        })->middleware('auth');
        Route::get('public/notes', fn () => FirmScopedNote::orderBy('id')->pluck('title'));
    });

    $this->franco = Firm::factory()->create(['slug' => 'franco']);
    $this->other = Firm::factory()->create(['slug' => 'outra']);

    $this->francoNote = FirmScopedNote::withoutGlobalScopes()->create(['firm_id' => $this->franco->id, 'title' => 'da Franco']);
    $this->otherNote = FirmScopedNote::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'title' => 'da outra']);

    $this->francoUser = User::factory()->create(['firm_id' => $this->franco->id]);
});

describe('a signed-in person', function () {
    it('lists only the rows of their own firm', function () {
        $this->actingAs($this->francoUser)->getJson('/api/test/notes')
            ->assertOk()
            ->assertExactJson(['da Franco']);
    });

    it('gets a 404 for a row of another firm, as if it did not exist', function () {
        $this->actingAs($this->francoUser)->getJson("/api/test/notes/{$this->otherNote->id}")->assertNotFound();
        $this->actingAs($this->francoUser)->getJson("/api/test/notes/{$this->francoNote->id}")->assertOk();
    });

    it('cannot update a row of another firm', function () {
        $this->actingAs($this->francoUser)
            ->patchJson("/api/test/notes/{$this->otherNote->id}", ['title' => 'tomada'])
            ->assertNotFound();

        expect(FirmScopedNote::withoutGlobalScopes()->find($this->otherNote->id)->title)->toBe('da outra');
    });

    // rnc-4 D5: the header used to be ignored for a signed-in person; it is now refused, so a session
    // on the wrong firm's host is told instead of quietly showing its own firm (FirmMismatchTest).
    it('is not moved to another firm by the X-Firm header: the mismatch is refused', function () {
        $this->actingAs($this->francoUser)
            ->withHeader('X-Firm', 'outra')
            ->getJson('/api/test/notes')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    });
});

describe('a public route', function () {
    it('reads the default firm when no header is sent', function () {
        $this->getJson('/api/test/public/notes')->assertOk()->assertExactJson(['da Franco']);
    });

    it('reads the firm the X-Firm header names', function () {
        $this->withHeader('X-Firm', 'outra')->getJson('/api/test/public/notes')->assertOk()->assertExactJson(['da outra']);
    });

    it('answers 404 to a firm that does not exist', function () {
        $this->withHeader('X-Firm', 'nenhuma')->getJson('/api/test/public/notes')->assertNotFound();
    });
});

describe('outside a request', function () {
    it('reads nothing while no firm is set', function () {
        expect(FirmScopedNote::count())->toBe(0);
    });

    it('stamps the current firm on a new row', function () {
        app(CurrentFirm::class)->runAs($this->other, function () {
            FirmScopedNote::create(['title' => 'nova']);
        });

        expect(FirmScopedNote::withoutGlobalScopes()->where('title', 'nova')->value('firm_id'))->toBe($this->other->id);
    });

    it('runs a command-style callback per firm and restores the previous firm afterwards', function () {
        $current = app(CurrentFirm::class);
        $seen = [];

        $current->forEachFirm(function (Firm $firm) use (&$seen) {
            $seen[$firm->slug] = FirmScopedNote::pluck('title')->all();
        });

        expect($seen)->toBe(['franco' => ['da Franco'], 'outra' => ['da outra']])
            ->and($current->get())->toBeNull();
    });
});
