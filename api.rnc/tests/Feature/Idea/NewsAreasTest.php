<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Idea\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Which part of the product an entry is about, in the Ideas board's own vocabulary — so an idea
 * about a screen and the entry announcing it are filed under the same word.
 */

function newsMaintainer(): User
{
    $role = Role::create(['name' => 'News maintainer '.uniqid()]);

    foreach (['news.manage', 'ideas.read'] as $key) {
        RolePermission::create(['role_id' => $role->id, 'permission_key' => $key, 'value' => true]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

describe('the areas an entry carries', function () {
    it('stores them and reads them back', function () {
        $response = $this->actingAs(newsMaintainer())->postJson('/api/news', [
            'title' => 'Timers on the board',
            'body' => '<p>Contam no servidor.</p>',
            'areas' => ['home', 'account'],
        ]);

        $response->assertCreated();
        expect($response->json('data.areas'))->toEqualCanonicalizing(['home', 'account']);
    });

    it('refuses a word that is not in the vocabulary', function () {
        $this->actingAs(newsMaintainer())->postJson('/api/news', [
            'title' => 'Qualquer coisa',
            'body' => '<p>Corpo.</p>',
            'areas' => ['home', 'inventado'],
        ])->assertStatus(422);
    });

    it('says the same area once, however many times it is sent', function () {
        $response = $this->actingAs(newsMaintainer())->postJson('/api/news', [
            'title' => 'Repetida',
            'body' => '<p>Corpo.</p>',
            'areas' => ['home', 'home'],
        ]);

        expect($response->json('data.areas'))->toBe(['home']);
    });

    it('replaces them on edit rather than adding to them', function () {
        $news = News::factory()->create();
        $user = newsMaintainer();

        $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'areas' => ['home'],
        ])->assertOk();

        $response = $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'areas' => ['home'],
        ]);

        expect($response->json('data.areas'))->toBe(['home']);
    });

    /*
     * A publish toggle is not a statement about areas. Sending nothing must leave them standing,
     * or flipping a draft live would quietly strip what the writer had filed it under.
     */
    it('leaves them alone when the request does not mention them', function () {
        $news = News::factory()->create();
        $user = newsMaintainer();

        $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'areas' => ['general'],
        ])->assertOk();

        $this->actingAs($user)->patchJson("/api/news/{$news->id}/publish", ['publish' => true])->assertOk();

        $entry = $this->actingAs($user)->getJson('/api/news')->json('data.0');
        expect($entry['areas'])->toBe(['general']);
    });

    it('accepts an empty list as a real answer', function () {
        $response = $this->actingAs(newsMaintainer())->postJson('/api/news', [
            'title' => 'No area',
            'body' => '<p>Corpo.</p>',
            'areas' => [],
        ]);

        expect($response->json('data.areas'))->toBe([]);
    });

    /*
     * The SPA is always multipart, because the entry may carry a cover — and multipart has no way
     * to write an empty array. Without `clearAreas` a writer who removed the last tag sent a
     * request indistinguishable from one that never mentioned areas, and the tags stayed put on a
     * 200. The two tests below are the multipart halves of the two JSON ones above.
     */
    it('takes clearAreas as the empty answer a multipart request cannot write', function () {
        $news = News::factory()->create();
        $user = newsMaintainer();

        $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'areas' => ['home'],
        ])->assertOk();

        $response = $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'clearAreas' => true,
        ]);

        expect($response->json('data.areas'))->toBe([]);
    });

    it('keeps clearAreas from overruling areas that were actually sent', function () {
        $news = News::factory()->create();
        $user = newsMaintainer();

        $response = $this->actingAs($user)->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => true,
            'areas' => ['general'],
            'clearAreas' => false,
        ]);

        expect($response->json('data.areas'))->toBe(['general']);
    });
});
