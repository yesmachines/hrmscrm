<?php

use App\Models\Designation;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access designations', function () {
    $this->get(route('designations.index'))->assertRedirect(route('login'));
});

test('authenticated users can view designations index', function () {
    $user = createHrmsLoginUser('admin');

    Designation::factory()->create([
        'title' => 'Software Architect',
        'shortcode' => 'SA',
        'department_id' => 1,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('designations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('designations/index')
            ->has('designations.data')
        );
});

test('authenticated users can view create designation page', function () {
    $user = createHrmsLoginUser('admin');

    $this->actingAs($user)
        ->get(route('designations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('designations/create')
            ->has('departments')
        );
});

test('authenticated users can create a new designation', function () {
    $user = createHrmsLoginUser('admin');

    $response = $this->actingAs($user)
        ->post(route('designations.store'), [
            'department_id' => 1,
            'title' => 'Chief Technology Officer',
            'shortcode' => 'CTO',
            'status' => 1,
        ]);

    $designation = Designation::query()->where('title', 'Chief Technology Officer')->first();
    expect($designation)->not->toBeNull()
        ->and($designation->shortcode)->toBe('CTO');

    $response->assertRedirect(route('designations.show', $designation));
});

test('authenticated users can view a designation show page', function () {
    $user = createHrmsLoginUser('admin');

    $designation = Designation::factory()->create([
        'title' => 'Quality Assurance Lead',
        'shortcode' => 'QAL',
    ]);

    $this->actingAs($user)
        ->get(route('designations.show', $designation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('designations/show')
            ->where('designation.title', 'Quality Assurance Lead')
        );
});

test('authenticated users can update an existing designation', function () {
    $user = createHrmsLoginUser('admin');

    $designation = Designation::factory()->create([
        'title' => 'Operations Officer',
        'shortcode' => 'OO',
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->put(route('designations.update', $designation), [
            'department_id' => 2,
            'title' => 'Senior Operations Officer',
            'shortcode' => 'SOO',
            'status' => 1,
        ])
        ->assertRedirect(route('designations.show', $designation));

    expect($designation->fresh()->title)->toBe('Senior Operations Officer')
        ->and($designation->fresh()->shortcode)->toBe('SOO');
});

test('authenticated users can delete a designation', function () {
    $user = createHrmsLoginUser('admin');

    $designation = Designation::factory()->create([
        'title' => 'Temporary Position',
        'shortcode' => 'TP',
    ]);

    $this->actingAs($user)
        ->delete(route('designations.destroy', $designation))
        ->assertRedirect(route('designations.index'));

    expect(Designation::query()->find($designation->id))->toBeNull();
});
