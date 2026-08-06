<?php

use App\Models\SalesCrm\User;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('admin users can authenticate using the login screen', function () {
    $user = createHrmsLoginUser('admin');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('hr users can authenticate using the login screen', function () {
    $user = createHrmsLoginUser('hr');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('non admin or hr users cannot authenticate', function () {
    $user = User::factory()->create();

    if (! DB::connection('salescrm')->table('roles')->where('name', 'salesmanager')->exists()) {
        DB::connection('salescrm')->table('roles')->insert([
            'name' => 'salesmanager',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    SalesCrmRoles::assignRoles($user->id, ['salesmanager']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('users can not authenticate with invalid password', function () {
    $user = createHrmsLoginUser('admin');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = createHrmsLoginUser('admin');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = createHrmsLoginUser('admin');

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
