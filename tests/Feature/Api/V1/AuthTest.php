<?php

use App\Models\SalesCrm\PersonalAccessToken;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
});

test('unauthenticated local setup endpoint creates an admin who can log in', function () {
    DB::connection('salescrm')->table('roles')->updateOrInsert(
        ['name' => 'admin', 'guard_name' => 'web'],
        ['created_at' => now(), 'updated_at' => now()],
    );

    $this->get('/dev/admin')->assertCreated()
        ->assertJsonPath('data.email', 'testadmin@gmail.com');

    $user = SalesCrmUser::query()->where('email', 'testadmin@gmail.com')->firstOrFail();

    expect(SalesCrmRoles::userHasLoginAccess($user->id))->toBeTrue()
        ->and(Hash::check('123456789', $user->password))->toBeTrue();

    $this->postJson('/api/v1/login', [
        'email' => 'testadmin@gmail.com',
        'password' => '123456789',
    ])->assertOk()
        ->assertJsonPath('data.user.email', 'testadmin@gmail.com');
});

test('admin setup endpoint is unavailable outside local and testing environments', function () {
    $this->app['env'] = 'production';

    $this->get('/dev/admin')->assertNotFound();

    expect(SalesCrmUser::query()->where('email', 'testadmin@gmail.com')->exists())->toBeFalse();
});

test('api login returns a sanctum token for valid credentials', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'mobile.user@example.com',
        'password' => 'Password123!',
        'device_name' => 'pixel-8',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'statusCode',
            'message',
            'data' => [
                'token',
                'token_type',
                'user' => ['id', 'name', 'email'],
            ],
        ])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'mobile.user@example.com');

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
});

test('api login rejects invalid credentials', function () {
    SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'mobile.user@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('authenticated api user can fetch their profile', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'mobile.user@example.com')
        ->assertJsonPath('data.name', 'Mobile User');
});

test('guests cannot access protected api routes', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('authenticated api user can logout and revoke the current token', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
