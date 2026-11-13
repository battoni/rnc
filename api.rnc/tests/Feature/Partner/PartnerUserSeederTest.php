<?php

use App\Models\User;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\PartnerAccessEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

/*
 * The partners' local logins (rnc-2 F1): `parceiro.1`…`parceiro.8`, one per seeded "(exemplo)"
 * partner, in its firm, role `parceiro`, password `seeding.password` — so a local run can sign in
 * as a partner. Each has its granted event (plan AM9). Never on production.
 */
beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

afterEach(fn () => CarbonImmutable::setTestNow());

it('gives each of the eight seeded partners its parceiro login', function () {
    $this->seed();

    $logins = User::query()->with('role')->whereNotNull('partner_id')->orderBy('username')->get();
    $partners = Partner::withoutGlobalScopes()->orderBy('id')->pluck('id')->all();

    expect($logins->pluck('username')->all())->toBe(['parceiro.1', 'parceiro.2', 'parceiro.3', 'parceiro.4', 'parceiro.5', 'parceiro.6', 'parceiro.7', 'parceiro.8'])
        ->and($logins->pluck('partner_id')->all())->toBe($partners)
        ->and($logins->every(fn (User $login) => $login->role?->slug === 'parceiro' && $login->firm_id === 1))->toBeTrue()
        ->and(Partner::withoutGlobalScopes()->whereNull('access_granted_at')->count())->toBe(0)
        ->and(PartnerAccessEvent::withoutGlobalScopes()->where('type', 'granted')->pluck('user_id')->sort()->values()->all())->toBe($logins->pluck('id')->sort()->values()->all());
});

it('lets a seeded partner sign in with the seeding password and reach its portal', function () {
    $this->seed();

    $this->postJson('/api/auth/login', ['username' => 'parceiro.3', 'password' => config('seeding.password')])->assertOk();

    $this->getJson('/api/portal/me')->assertOk()->assertJsonPath('data.partner.name', 'NexoERP (exemplo)');
});

it('seeds no partner login on a production database', function () {
    app()->detectEnvironment(fn () => 'production');
    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(User::whereNotNull('partner_id')->count())->toBe(0);
});
