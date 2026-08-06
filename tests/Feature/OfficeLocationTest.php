<?php

use App\Models\OfficeLocation;
use App\Models\Organisation;
use App\Models\SalesCrm\Country;

test('guests cannot access office locations', function () {
    $this->get(route('office-locations.index'))->assertRedirect(route('login'));
});

test('authenticated users can manage office locations', function () {
    $admin = createHrmsLoginUser('admin');

    $organisation = Organisation::query()->create([
        'org_name' => 'Test Org',
        'short_name' => 'TO',
        'status' => 1,
    ]);

    $country = Country::query()->where('status', 1)->orderBy('id')->first();

    if ($country === null) {
        $country = Country::query()->create([
            'name' => 'United Arab Emirates',
            'code' => 'AE',
            'status' => 1,
        ]);
    }

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('office-locations.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('office-locations.store'), [
            'organisation_id' => $organisation->id,
            'office_name' => 'Dubai HQ',
            'country_id' => $country->id,
            'city' => 'Dubai',
            'address' => 'Business Bay',
        ])
        ->assertRedirect();

    $location = OfficeLocation::query()->where('office_name', 'Dubai HQ')->first();
    expect($location)->not->toBeNull()
        ->and($location->organisation_id)->toBe($organisation->id)
        ->and($location->city)->toBe('Dubai');

    $this->actingAs($admin)
        ->put(route('office-locations.update', $location), [
            'organisation_id' => $organisation->id,
            'office_name' => 'Dubai Head Office',
            'country_id' => $country->id,
            'city' => 'Dubai',
            'address' => 'Downtown',
        ])
        ->assertRedirect(route('office-locations.show', $location));

    expect($location->fresh()->office_name)->toBe('Dubai Head Office');

    $this->actingAs($admin)
        ->delete(route('office-locations.destroy', $location))
        ->assertRedirect(route('office-locations.index'));

    expect(OfficeLocation::query()->find($location->id))->toBeNull();

    $organisation->delete();
});
