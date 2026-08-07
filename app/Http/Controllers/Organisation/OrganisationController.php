<?php

namespace App\Http\Controllers\Organisation;

use App\Actions\StoreOrganisationLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreOrganisationRequest;
use App\Http\Requests\Organisation\UpdateOrganisationRequest;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function __construct(
        private StoreOrganisationLogo $storeOrganisationLogo,
    ) {}

    public function index(): Response
    {
        $organisations = Organisation::query()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Organisation $organisation): array => $this->organisationPayload($organisation));

        return Inertia::render('organisations/index', [
            'organisations' => $organisations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('organisations/create');
    }

    public function store(StoreOrganisationRequest $request): RedirectResponse
    {
        $payload = $request->organisationPayload();

        if ($request->hasFile('logo')) {
            $payload['logo'] = $this->storeOrganisationLogo->handle($request->file('logo'));
        }

        $organisation = Organisation::query()->create($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organisation created.'),
        ]);

        return to_route('organisations.show', $organisation);
    }

    public function show(Organisation $organisation): Response
    {
        return Inertia::render('organisations/show', [
            'organisation' => $this->organisationPayload($organisation),
        ]);
    }

    public function edit(Organisation $organisation): Response
    {
        return Inertia::render('organisations/edit', [
            'organisation' => $this->organisationPayload($organisation),
        ]);
    }

    public function update(
        UpdateOrganisationRequest $request,
        Organisation $organisation,
    ): RedirectResponse {
        $payload = $request->organisationPayload();

        if ($request->hasFile('logo')) {
            $this->storeOrganisationLogo->delete($organisation->logo);
            $payload['logo'] = $this->storeOrganisationLogo->handle($request->file('logo'));
        }

        $organisation->update($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organisation updated.'),
        ]);

        return to_route('organisations.show', $organisation);
    }

    public function destroy(Organisation $organisation): RedirectResponse
    {
        if ($organisation->officeLocations()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete an organisation that has office locations.'),
            ]);

            return back();
        }

        $this->storeOrganisationLogo->delete($organisation->logo);
        $organisation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organisation deleted.'),
        ]);

        return to_route('organisations.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function organisationPayload(Organisation $organisation): array
    {
        return [
            'id' => $organisation->id,
            'org_name' => $organisation->org_name,
            'short_name' => $organisation->short_name,
            'logo' => $this->storeOrganisationLogo->url($organisation->logo),
            'status' => $organisation->status,
        ];
    }
}
