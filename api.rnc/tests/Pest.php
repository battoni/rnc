<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
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
| MySQL stores a JSON object with its keys re-ordered (by length, then by name), SQLite keeps them as
| written: `['from' => 'lead', 'to' => 'x', 'via' => 'y']` comes back as `to, via, from` (rnc-4 F7b).
| Key order of a JSON object is not part of any contract, so a value read from a JSON column is
| compared with this: identical (`toBe`) once the keys of every object are sorted — lists keep
| their order, scalars keep their type.
*/
function sortJsonObjectKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $sorted = array_map(sortJsonObjectKeys(...), $value);

    if (! array_is_list($sorted)) {
        ksort($sorted);
    }

    return $sorted;
}

expect()->extend('toBeIgnoringKeyOrder', function (array $expected) {
    expect(sortJsonObjectKeys($this->value))->toBe(sortJsonObjectKeys($expected));

    return $this;
});

TestResponse::macro('assertJsonPathIgnoringKeyOrder', function (string $path, array $expected) {
    /** @var TestResponse $this */
    expect($this->json($path))->toBeIgnoringKeyOrder($expected);

    return $this;
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

/**
 * A member of a firm in one of the seeded roles (`RoleSeeder` must have run), for the Reforma
 * assistant's tests (rnc-3 F4): six files ask "who is acting" — a specialist, the team, Reinaldo,
 * the operator, another firm's specialist.
 *
 * @param  array<string, mixed>  $attributes
 */
function assistantPerson(string $role, bool $specialist = false, ?Firm $firm = null, array $attributes = []): User
{
    return User::factory()->create([
        'firm_id' => ($firm ?? Firm::where('slug', 'franco')->firstOrFail())->id,
        'role_id' => Role::where('slug', $role)->value('id'),
        'is_specialist' => $specialist,
        ...$attributes,
    ]);
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
