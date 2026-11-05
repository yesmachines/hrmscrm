<?php

namespace App\Http\Controllers\Designation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designation\StoreDesignationRequest;
use App\Http\Requests\Designation\UpdateDesignationRequest;
use App\Models\Designation;
use App\Models\SalesCrm\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $departments = $this->departments();
        $departmentsMap = collect($departments)->keyBy('id');

        $designations->setCollection(
            $designations->getCollection()->map(fn (Designation $designation): array => [
                'id' => $designation->id,
                'department_id' => $designation->department_id,
                'title' => $designation->title,
                'shortcode' => $designation->shortcode,
                'status' => $designation->status,
                'department' => $departmentsMap->get($designation->department_id),
            ])
        );

        return Inertia::render('designations/index', [
            'designations' => $designations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('designations/create', [
            'departments' => $this->departments(),
        ]);
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $designation = Designation::query()->create($request->designationPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Designation created.'),
        ]);

        return to_route('designations.index');
    }

    public function show(Designation $designation): Response
    {
        $department = collect($this->departments())
            ->firstWhere('id', $designation->department_id);

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
            'departments' => $this->departments(),
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

    public function updateStatus(Request $request, Designation $designation): RedirectResponse
    {
        $validated = $request->validate(['status' => 'required|in:0,1']);
        $designation->update(['status' => (int) $validated['status']]);

        return back();
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function departments(): array
    {
        if (! Schema::connection('salescrm')->hasTable('departments')) {
            return [];
        }

        return Department::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ])
            ->all();
    }
}
