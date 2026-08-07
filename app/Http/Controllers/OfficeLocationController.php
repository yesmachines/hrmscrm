<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfficeLocationRequest;
use App\Http\Requests\UpdateOfficeLocationRequest;
use App\Models\OfficeLocation;
use App\Models\Organisation;
use App\Models\SalesCrm\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OfficeLocationController extends Controller
{
    public function index(): Response
    {
        $locations = OfficeLocation::query()
            ->with('organisation:id,org_name,short_name')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $countries = $this->countriesById(
            $locations->getCollection()->pluck('country_id')->filter()->all()
        );

        $locations->setCollection(
            $locations->getCollection()->map(
                fn (OfficeLocation $location): array => $this->officeLocationPayload(
                    $location,
                    $countries->get($location->country_id),
                )
            )
        );

        return Inertia::render('office-locations/index', [
            'officeLocations' => $locations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('office-locations/create', [
            'organisations' => $this->organisations(),
            'countries' => $this->countries(),
        ]);
    }

    public function store(StoreOfficeLocationRequest $request): RedirectResponse
    {
        $location = OfficeLocation::query()->create($request->officeLocationPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Office location created.'),
        ]);

        return to_route('office-locations.show', $location);
    }

    public function show(OfficeLocation $office_location): Response
    {
        $office_location->load('organisation:id,org_name,short_name');

        return Inertia::render('office-locations/show', [
            'officeLocation' => $this->officeLocationPayload(
                $office_location,
                $this->countryOption($office_location->country_id),
            ),
        ]);
    }

    public function edit(OfficeLocation $office_location): Response
    {
        $office_location->load('organisation:id,org_name,short_name');

        return Inertia::render('office-locations/edit', [
            'officeLocation' => $this->officeLocationPayload(
                $office_location,
                $this->countryOption($office_location->country_id),
            ),
            'organisations' => $this->organisations(),
            'countries' => $this->countries(),
        ]);
    }

    public function update(
        UpdateOfficeLocationRequest $request,
        OfficeLocation $office_location,
    ): RedirectResponse {
        $office_location->update($request->officeLocationPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Office location updated.'),
        ]);

        return to_route('office-locations.show', $office_location);
    }

    public function destroy(OfficeLocation $office_location): RedirectResponse
    {
        $office_location->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Office location deleted.'),
        ]);

        return to_route('office-locations.index');
    }

    /**
     * @return list<array{id: int, name: string, short_name: string}>
     */
    private function organisations(): array
    {
        return Organisation::query()
            ->orderByDesc('status')
            ->orderBy('org_name')
            ->get(['id', 'org_name', 'short_name', 'status'])
            ->map(fn (Organisation $organisation): array => [
                'id' => $organisation->id,
                'name' => $organisation->status === 1
                    ? $organisation->org_name
                    : $organisation->org_name.' (Inactive)',
                'short_name' => $organisation->short_name,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function countries(): array
    {
        return Country::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array{id: int, name: string, code: string}>
     */
    private function countriesById(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return collect();
        }

        return Country::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Country $country): array => [
                $country->id => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'code' => $country->code,
                ],
            ]);
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function countryOption(?int $countryId): ?array
    {
        if (! $countryId) {
            return null;
        }

        return $this->countriesById([$countryId])->get($countryId);
    }

    /**
     * @param  array{id: int, name: string, code: string}|null  $country
     * @return array<string, mixed>
     */
    private function officeLocationPayload(OfficeLocation $location, ?array $country = null): array
    {
        return [
            'id' => $location->id,
            'organisation_id' => $location->organisation_id,
            'office_name' => $location->office_name,
            'country_id' => $location->country_id,
            'city' => $location->city,
            'address' => $location->address,
            'organisation' => $location->organisation
                ? [
                    'id' => $location->organisation->id,
                    'name' => $location->organisation->org_name,
                    'short_name' => $location->organisation->short_name,
                ]
                : null,
            'country' => $country,
        ];
    }
}
