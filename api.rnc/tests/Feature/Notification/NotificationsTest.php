<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Notification\Notifications\MeetingBookedNotification;
use App\Modules\Notification\Notifications\NewLeadNotification;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The bell (rnc-1 R17, plan AM7/AM8): a staff member's own database notifications.
 *
 *   GET   /api/notifications             the unread count and the latest 20
 *   PATCH /api/notifications/{id}/read   one marked as read
 *   POST  /api/notifications/read-all    all marked as read
 *
 * Behind `notifications.read`; somebody else's notification is a 404, never a 403.
 */
function staffMember(string $role = 'equipe'): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
});

it('lists my notifications, newest first, with the unread count', function () {
    $member = staffMember();
    $member->notify(new NewLeadNotification(7, 'Padaria Sol', 'comercio'));
    $this->travel(1)->minutes();
    $member->notify(new MeetingBookedNotification(8, 'Clínica Horizonte', 'Juliana Reis', '2026-11-24T14:30:00-03:00'));
    staffMember()->notify(new NewLeadNotification(9, 'De outra pessoa', null));

    $response = $this->actingAs($member)->getJson('/api/notifications')->assertOk();

    expect($response->json('data.unread_count'))->toBe(2)
        ->and($response->json('data.items'))->toHaveCount(2)
        ->and($response->json('data.items.0'))->toMatchArray([
            'kind' => 'meeting_booked',
            'lead_id' => 8,
            'company' => 'Clínica Horizonte',
            'specialist' => 'Juliana Reis',
            'starts_at' => '2026-11-24T14:30:00-03:00',
            'read_at' => null,
        ])
        ->and($response->json('data.items.1'))->toMatchArray(['kind' => 'new_lead', 'company' => 'Padaria Sol', 'sector' => 'comercio']);
});

it('marks one as read, then all', function () {
    $member = staffMember('ceo');
    $member->notify(new NewLeadNotification(7, 'Padaria Sol', 'comercio'));
    $member->notify(new NewLeadNotification(8, 'Mercado Bom', 'comercio'));
    $first = $member->notifications()->firstOrFail();

    $this->actingAs($member)->patchJson("/api/notifications/{$first->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $first->id)
        ->assertJsonPath('data.read_at', fn (?string $readAt) => $readAt !== null);

    expect($member->unreadNotifications()->count())->toBe(1);

    $this->actingAs($member)->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.unread_count', 0);

    expect($member->unreadNotifications()->count())->toBe(0);
});

it('answers 404 for somebody else\'s notification', function () {
    $owner = staffMember();
    $owner->notify(new NewLeadNotification(7, 'Padaria Sol', 'comercio'));

    $this->actingAs(staffMember())->patchJson('/api/notifications/'.$owner->notifications()->firstOrFail()->id.'/read')->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

it('needs a session and notifications.read', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();

    $this->actingAs(User::factory()->create())->getJson('/api/notifications')->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson('/api/notifications/read-all')->assertForbidden();
});
