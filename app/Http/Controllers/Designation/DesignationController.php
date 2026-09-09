<?php

namespace App\Http\Controllers\Designation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designation\StoreDesignationRequest;
use App\Http\Requests\Designation\UpdateDesignationRequest;
use App\Models\Designation;
use App\Models\SalesCrm\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function index(): Response
    {
        $search = request('search');

        $designations = Designation::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('shortcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        $divisions = $this->divisions();
        $divisionsMap = collect($divisions)->keyBy('id');

        $designations->setCollection(
            $designations->getCollection()->map(fn (Designation $designation): array => [
                'id' => $designation->id,
                'department_id' => $designation->department_id,
                'title' => $designation->title,
                'shortcode' => $designation->shortcode,
                'status' => $designation->status,
                'department' => $divisionsMap->get($designation->department_id),
            ])
        );

        return Inertia::render('designations/index', [
            'designations' => $designations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('designations/create', [
            'departments' => $this->divisions(),
        ]);
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $designation = Designation::query()->create($request->designationPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Designation created.'),
        ]);

        return to_route('designations.show', $designation);
    }

    public function show(Designation $designation): Response
    {
        $divisions = $this->divisions();
        $department = collect($divisions)->firstWhere('id', $designation->department_id);

        return Inertia::render('designations/show', [
            'designation' => [
                'id' => $designation->id,
                'department_id' => $designation->department_id,
                'title' => $designation->title,
                'shortcode' => $designation->shortcode,
                'status' => $designation->status,
                'department' => $department,
                'created_at' => $designation->created_at?->format('Y-m-d H:i'),
                'updated_at' => $designation->updated_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function edit(Designation $designation): Response
    {
        return Inertia::render('designations/edit', [
            'designation' => [
                'id' => $designation->id,
                'department_id' => $designation->department_id,
                'title' => $designation->title,
                'shortcode' => $designation->shortcode,
                'status' => $designation->status,
            ],
            'departments' => $this->divisions(),
        ]);
    }

    public function update(
        UpdateDesignationRequest $request,
        Designation $designation,
    ): RedirectResponse {
        $designation->update($request->designationPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Designation updated.'),
        ]);

        return to_route('designations.show', $designation);
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $designation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Designation deleted.'),
        ]);

        return to_route('designations.index');
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function divisions(): array
    {
        if (! Schema::connection('salescrm')->hasTable('divisions')) {
            return [];
        }

        return Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Division $division): array => [
                'id' => $division->id,
                'name' => $division->name,
                'code' => $division->code,
            ])
            ->all();
    }
}
