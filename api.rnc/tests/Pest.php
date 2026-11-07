<?php

use App\Models\User;
use App\Modules\Auth\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| People
|--------------------------------------------------------------------------
|
| Personas, not roles: a test says WHO is acting, and the permission list behind the name is
| the only place that has to change when a key is renamed. They live here rather than in a
| single test file because more than one feature area needs them — a helper declared inside a
| spec is global by accident, and two specs declaring the same name is a fatal error.
|
*/

function userWithPermissions(array $permissionKeys): User
{
    $user = User::factory()->create();

    foreach ($permissionKeys as $permissionKey) {
        UserPermission::create(['user_id' => $user->id, 'permission_key' => $permissionKey, 'value' => true]);
    }

    return $user;
}

/** Reads the board and can post to it — the ordinary member of the product's audience. */
function ideaContributor(): User
{
    return userWithPermissions(['ideas.read', 'ideas.create']);
}

/** Runs the board: status, the public note, hiding, merging. */
function ideaMaintainer(): User
{
    return userWithPermissions(['ideas.read', 'ideas.create', 'ideas.manage']);
}

/** Writes the changelog. Reads the board because an entry can credit the idea that started it. */
function newsWriter(): User
{
    return userWithPermissions(['ideas.read', 'news.manage']);
}
