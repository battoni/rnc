<?php

use App\Models\User;
use App\Modules\Auth\Models\UserPermission;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
| No test reaches the network (rnc-3 plan P7, AM18; A27). A request no test faked throws
| `StrayRequestException` instead of leaving the machine — whether or not the test file names
| `Http::`. Unit tests boot no application, so they cannot reach it through the client at all.
*/
pest()->beforeEach(fn () => Http::preventStrayRequests())->in('Feature');

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

/*
|--------------------------------------------------------------------------
| Database errors
|--------------------------------------------------------------------------
|
| A unique violation as PostgreSQL reports it (SQLSTATE 23505). SQLite and MySQL say 23000, so a
| catch that compares the code with '23000' passes every SQLite test and answers 500 on pgsql:
| a repository double throws this to prove the catch keys on the exception class, not the code.
|
*/

function postgresUniqueViolation(string $table): UniqueConstraintViolationException
{
    $pdo = new class('SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint') extends PDOException
    {
        public function __construct(string $message)
        {
            parent::__construct($message);
            $this->code = '23505';
        }
    };

    return new UniqueConstraintViolationException('pgsql', "insert into \"{$table}\"", [], $pdo);
}
