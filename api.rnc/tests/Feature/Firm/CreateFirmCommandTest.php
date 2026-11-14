<?php

use App\Models\User;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * A22 (rnc-4 R21, R13, D17): `firm:create acme --name=… --manager=… --email=…` makes a firm ready to
 * sign in to — the default palette, core + `briefing`, the six default sector pages, consent v1 copied
 * from the platform default, a `gestao` manager whose password is printed once and then signs in with
 * `X-Firm: acme` — and refuses a reserved, malformed or taken slug without writing anything.
 */
const CREATE_FIRM_SECTOR_SLUGS = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'];

const CREATE_FIRM_PASSWORD_LINE = '/Senha \(mostrada uma vez\): (\S+)/';

/** @param  array<string, string|bool>  $options */
function createFirm(string $slug = 'acme', array $options = []): array
{
    $exitCode = Artisan::call('firm:create', [
        'slug' => $slug,
        '--name' => 'Acme Contábil',
        '--manager' => 'helena.acme',
        '--email' => 'helena@acme.exemplo.com.br',
        ...$options,
    ]);

    return [$exitCode, Artisan::output()];
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
});

describe('a new firm (A22)', function () {
    it('creates the firm with the default palette and core + briefing', function () {
        [$exitCode] = createFirm();

        expect($exitCode)->toBe(0);
        $acme = Firm::where('slug', 'acme')->firstOrFail();
        expect($acme->name)->toBe('Acme Contábil')
            ->and($acme->logo_text)->toBe('Acme Contábil')
            ->and($acme->short_name)->toBe('Acme Contábil')
            ->and($acme->brand_primary)->toBe('#1f4e79')
            ->and($acme->brand_accent)->toBe('#f2a900')
            ->and($acme->is_platform)->toBeFalse()
            ->and($acme->modules)->toBe(['briefing'])
            ->and($acme->mailbox)->toBe('helena@acme.exemplo.com.br')
            ->and($acme->business_hours)->not->toBeEmpty();
    });

    it('gives the firm the six default sector pages, in order', function () {
        createFirm();
        $acme = Firm::where('slug', 'acme')->firstOrFail();

        $slugs = DB::table('sector_pages')->where('firm_id', $acme->id)->orderBy('position')->pluck('slug')->all();
        $otherFirmsPages = DB::table('sector_pages')->where('firm_id', '!=', $acme->id)->count();

        expect($slugs)->toBe(CREATE_FIRM_SECTOR_SLUGS)
            ->and($otherFirmsPages)->toBe(0)
            ->and(DB::table('sector_pages')->where('firm_id', $acme->id)->where('slug', 'comercio')->value('title'))
            ->toBe('Reforma Tributária para o comércio');
    });

    it('starts consent at version 1 equal to the platform default', function () {
        createFirm();
        $acme = Firm::where('slug', 'acme')->firstOrFail();

        $versions = DB::table('firm_consent_versions')->where('firm_id', $acme->id)->get();

        expect($versions)->toHaveCount(1)
            ->and((int) $versions->first()->version)->toBe(1)
            ->and($versions->first()->text)->toBe(app(PlatformSettings::class)->consentDefault());
    });

    it('makes a gestao manager in the firm, not a specialist, and no specialist row', function () {
        createFirm();
        $acme = Firm::where('slug', 'acme')->firstOrFail();

        $manager = User::where('username', 'helena.acme')->with('role')->firstOrFail();

        expect($manager->firm_id)->toBe($acme->id)
            ->and($manager->role->slug)->toBe('gestao')
            ->and($manager->email)->toBe('helena@acme.exemplo.com.br')
            ->and($manager->is_specialist)->toBeFalse()
            ->and(Specialist::withoutGlobalScopes()->where('user_id', $manager->id)->exists())->toBeFalse();
    });

    it('prints a 16-character password once, which signs in on acme and nowhere else', function () {
        [, $output] = createFirm();

        expect(preg_match(CREATE_FIRM_PASSWORD_LINE, $output, $match))->toBe(1);
        $password = $match[1];
        expect(mb_strlen($password))->toBe(16)
            ->and($output)->toContain('acme.localhost');

        $this->withHeader('X-Firm', 'franco')
            ->postJson('/api/auth/login', ['username' => 'helena.acme', 'password' => $password])
            ->assertStatus(422);

        $this->flushHeaders()->withHeader('X-Firm', 'acme')
            ->postJson('/api/auth/login', ['username' => 'helena.acme', 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.username', 'helena.acme');
    });

    it('with --specialist makes the manager bookable on the firm’s business hours', function () {
        createFirm(options: ['--specialist' => true]);
        $acme = Firm::where('slug', 'acme')->firstOrFail();
        $manager = User::where('username', 'helena.acme')->firstOrFail();

        $specialist = Specialist::withoutGlobalScopes()->where('user_id', $manager->id)->with('hours')->firstOrFail();
        $expected = collect($acme->business_hours)
            ->flatMap(fn (array $hours) => collect($hours['weekdays'])->map(fn (int $weekday) => [$weekday, $hours['from'], $hours['to']]))
            ->sort()->values()->all();
        $hours = $specialist->hours
            ->map(fn ($hour) => [(int) $hour->weekday, substr((string) $hour->opens_at, 0, 5), substr((string) $hour->closes_at, 0, 5)])
            ->sort()->values()->all();

        expect($manager->is_specialist)->toBeTrue()
            ->and($specialist->firm_id)->toBe($acme->id)
            ->and($hours)->toBe($expected)
            ->and($hours)->not->toBeEmpty();
    });
});

describe('refusals (A22)', function () {
    it('refuses a reserved, malformed or taken slug and writes nothing', function (string $slug, string $key) {
        $firms = Firm::count();
        $users = User::count();

        [$exitCode, $output] = createFirm($slug);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain($key)
            ->and(Firm::count())->toBe($firms)
            ->and(User::count())->toBe($users)
            ->and(DB::table('sector_pages')->count())->toBe(0);
    })->with([
        'reserved www' => ['www', 'firms.slugReserved'],
        'reserved hub' => ['hub', 'firms.slugReserved'],
        'malformed Acme!' => ['Acme!', 'firms.slugInvalid'],
        'malformed leading dash' => ['-acme', 'firms.slugInvalid'],
        'taken franco' => ['franco', 'firms.slugTaken'],
    ]);

    it('refuses a taken manager username and leaves no firm behind', function () {
        User::factory()->create(['username' => 'helena.acme']);
        $firms = Firm::count();

        [$exitCode, $output] = createFirm();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('users.form.usernameTaken')
            ->and(Firm::count())->toBe($firms);
    });

    it('refuses a taken or malformed e-mail', function (string $email, string $key) {
        User::factory()->create(['email' => 'taken@acme.exemplo.com.br']);

        [$exitCode, $output] = createFirm(options: ['--email' => $email]);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain($key)
            ->and(Firm::where('slug', 'acme')->exists())->toBeFalse();
    })->with([
        'taken' => ['taken@acme.exemplo.com.br', 'users.form.emailTaken'],
        'malformed' => ['not-an-email', 'users.form.emailInvalid'],
    ]);

    it('refuses without a name or a manager', function (string $option) {
        [$exitCode] = createFirm(options: [$option => '']);

        expect($exitCode)->toBe(1)
            ->and(Firm::where('slug', 'acme')->exists())->toBeFalse();
    })->with(['--name', '--manager']);

    it('rolls the firm back when a later step fails', function () {
        $this->mock(PlatformSettings::class)->shouldReceive('consentDefault')->andThrow(new RuntimeException('boom'));

        expect(fn () => createFirm())->toThrow(RuntimeException::class);

        expect(Firm::where('slug', 'acme')->exists())->toBeFalse()
            ->and(User::where('username', 'helena.acme')->exists())->toBeFalse();
    });
});
