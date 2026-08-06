<?php

use App\Models\Organisation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot access organisations', function () {
    $this->get(route('organisations.index'))->assertRedirect(route('login'));
});

test('authenticated users can manage organisations with logo upload', function () {
    Storage::fake('public');

    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('organisations.index'))
        ->assertOk();

    $logo = UploadedFile::fake()->image('logo.png', 120, 120);

    $this->actingAs($admin)
        ->post(route('organisations.store'), [
            'org_name' => 'Genesys Labs',
            'short_name' => 'GL',
            'logo' => $logo,
            'status' => 1,
        ])
        ->assertRedirect();

    $organisation = Organisation::query()->where('short_name', 'GL')->first();
    expect($organisation)->not->toBeNull()
        ->and($organisation->org_name)->toBe('Genesys Labs')
        ->and($organisation->logo)->toStartWith('organisations/');

    Storage::disk('public')->assertExists($organisation->logo);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('organisations.show', $organisation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisations/show')
            ->where('organisation.logo', fn ($value) => is_string($value) && str_contains($value, '/storage/organisations/'))
        );

    $this->actingAs($admin)
        ->put(route('organisations.update', $organisation), [
            'org_name' => 'Genesys Labs LLC',
            'short_name' => 'GL',
            'status' => 1,
        ])
        ->assertRedirect(route('organisations.show', $organisation));

    expect($organisation->fresh()->org_name)->toBe('Genesys Labs LLC');

    $this->actingAs($admin)
        ->delete(route('organisations.destroy', $organisation))
        ->assertRedirect(route('organisations.index'));

    expect(Organisation::query()->find($organisation->id))->toBeNull();
});
