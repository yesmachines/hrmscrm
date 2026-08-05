<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeProfileRequest;
use App\Http\Requests\UpdateEmployeeProfileRequest;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeProfileController extends Controller
{
    public function index(): Response
    {
        $employees = EmployeeProfile::query()
            ->with(['employee:id,name,email'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/create', [
            'users' => $this->availableUsers(),
        ]);
    }

    public function store(StoreEmployeeProfileRequest $request): RedirectResponse
    {
        $profile = EmployeeProfile::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee profile created.'),
        ]);

        return to_route('employees.show', $profile);
    }

    public function show(EmployeeProfile $employeeProfile): Response
    {
        $employeeProfile->load(['employee:id,name,email']);

        return Inertia::render('employees/show', [
            'employee' => $employeeProfile,
        ]);
    }

    public function edit(EmployeeProfile $employeeProfile): Response
    {
        $employeeProfile->load(['employee:id,name,email']);

        return Inertia::render('employees/edit', [
            'employee' => $employeeProfile,
        ]);
    }

    public function update(
        UpdateEmployeeProfileRequest $request,
        EmployeeProfile $employeeProfile,
    ): RedirectResponse {
        $employeeProfile->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee profile updated.'),
        ]);

        return to_route('employees.show', $employeeProfile);
    }

    public function destroy(EmployeeProfile $employeeProfile): RedirectResponse
    {
        $employeeProfile->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee profile deleted.'),
        ]);

        return to_route('employees.index');
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function availableUsers(): array
    {
        return User::query()
            ->whereDoesntHave('employeeProfile')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
