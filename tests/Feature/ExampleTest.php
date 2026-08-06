<?php

test('guests are redirected from home to login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('authenticated users are redirected from home to dashboard', function () {
    $user = createHrmsLoginUser('admin');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
