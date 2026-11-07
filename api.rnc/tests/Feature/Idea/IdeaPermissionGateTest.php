<?php

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Database\Seeders\ManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The board's three keys divide three genuinely different acts, and the point of splitting them
 * is what each one REFUSES. A user holding one and not the others is the only way to assert it.
 */
describe('the ideas entitlement', function () {
    it('closes the whole board to an account holding none of the keys', function () {
        $stranger = userWithPermissions([]);
        $idea = Idea::factory()->create();

        $this->actingAs($stranger)->getJson('/api/ideas')->assertForbidden();
        $this->actingAs($stranger)->getJson("/api/ideas/{$idea->id}")->assertForbidden();
        $this->actingAs($stranger)->getJson('/api/news')->assertForbidden();
    });

    it('lets a reader read the board but not write to it', function () {
        $reader = userWithPermissions(['ideas.read']);
        Idea::factory()->create();

        $this->actingAs($reader)->getJson('/api/ideas')->assertOk();

        $this->actingAs($reader)->postJson('/api/ideas', [
            'title' => 'Should not get in',
            'description' => 'Descrição',
            'type' => IdeaType::IDEA->value,
        ])->assertForbidden();

        expect(Idea::count())->toBe(1);
    });

    it('still lets a reader support and comment, because those are not writing an idea', function () {
        $reader = userWithPermissions(['ideas.read']);
        $idea = Idea::factory()->create();

        $this->actingAs($reader)->postJson("/api/ideas/{$idea->id}/vote")->assertOk();
        $this->actingAs($reader)->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Apoio.'])
            ->assertStatus(201);
    });

    it('closes every moderation act to someone who can read and create', function () {
        $contributor = userWithPermissions(['ideas.read', 'ideas.create']);
        $duplicate = Idea::factory()->create();
        $target = Idea::factory()->create();

        $this->actingAs($contributor)->patchJson("/api/ideas/{$duplicate->id}/status", [
            'status' => IdeaStatus::PLANNED->value,
        ])->assertForbidden();

        $this->actingAs($contributor)->patchJson("/api/ideas/{$duplicate->id}/hide")->assertForbidden();

        $this->actingAs($contributor)->postJson("/api/ideas/{$duplicate->id}/merge", [
            'targetId' => $target->id,
        ])->assertForbidden();
    });

    it('refuses an anonymous request outright', function () {
        $idea = Idea::factory()->create();

        $this->getJson('/api/ideas')->assertUnauthorized();
        $this->postJson("/api/ideas/{$idea->id}/vote")->assertUnauthorized();
    });

    it('gives the seeded member role read and create but never manage', function () {
        $this->seed(ManagerSeeder::class);

        $role = Role::where('slug', 'user')->first();
        $keys = RolePermission::where('role_id', $role->id)
            ->pluck('permission_key')
            ->all();

        expect($keys)->toContain('ideas.read');
        expect($keys)->toContain('ideas.create');
        expect($keys)->not->toContain('ideas.manage');
    });
});
