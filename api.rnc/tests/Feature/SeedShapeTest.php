<?php

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The seeded install, stated exactly. It is small enough to state, and stating it is the point: an
 * install that quietly grows demo accounts is an install with extra passwords.
 *
 * Two shapes. Outside production the team the pilot is shown with is seeded too — Reinaldo, one
 * team member and the four specialists a visitor books — and on a production database only the
 * operator's account exists, because those passwords are the shared local default.
 */
describe('database seed', function () {
    it('creates Franco as the first and only firm', function () {
        $this->seed();

        expect(Firm::pluck('slug')->all())->toBe(['franco'])
            ->and(Firm::first()->id)->toBe(1);
    });

    it('creates the operator, Reinaldo, the team and four specialists, all in Franco', function () {
        $this->seed();

        expect(User::pluck('username')->sort()->values()->all())->toBe([
            'battoni',
            'carlos.prado',
            'juliana.reis',
            'mariana.toledo',
            'patricia',
            'rafael.antunes',
            'reinaldo',
        ])
            ->and(User::whereNull('firm_id')->count())->toBe(0)
            ->and(User::where('is_specialist', true)->pluck('username')->sort()->values()->all())
            ->toBe(['carlos.prado', 'juliana.reis', 'mariana.toledo', 'rafael.antunes']);
    });

    it('gives every seeded account the role it is shown with', function () {
        $this->seed();

        $roles = User::with('role')->get()->mapWithKeys(fn (User $user) => [$user->username => $user->role?->slug]);

        expect($roles['battoni'])->toBe('operador')
            ->and($roles['reinaldo'])->toBe('ceo')
            ->and($roles['patricia'])->toBe('equipe')
            ->and($roles['mariana.toledo'])->toBe('equipe');
    });

    it('seeds only the operator on a production database', function () {
        app()->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` would stop to ask "really, in production?".
        app(DatabaseSeeder::class)->setContainer(app())->__invoke();

        expect(User::pluck('username')->all())->toBe(['battoni']);
    });

    it('leaves no throwaway test account behind', function () {
        $this->seed();

        expect(User::where('username', 'test-user')->orWhere('email', 'test@example.com')->count())->toBe(0);
    });
});
