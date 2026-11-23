<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveTemplateRequest;
use App\Http\Requests\Leave\UpdateLeaveTemplateRequest;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = LeaveTemplate::query()
            ->with(['leaveType:id,leave_name,code', 'organisation:id,org_name,short_name'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (LeaveTemplate $template): array => $this->payload($template));

        return Inertia::render('leave-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('leave-templates/create', [
            'leaveTypes' => $this->leaveTypes(),
            'organisations' => $this->organisations(),
        ]);
    }

    public function store(StoreLeaveTemplateRequest $request): RedirectResponse
    {
        $template = LeaveTemplate::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave template created.'),
        ]);

        return to_route('leave-templates.show', $template);
    }

    public function show(LeaveTemplate $leave_template): Response
    {
        $leave_template->load(['leaveType:id,leave_name,code', 'organisation:id,org_name,short_name']);

        return Inertia::render('leave-templates/show', [
            'template' => $this->payload($leave_template),
            'companyLogoUrl' => asset('yes_machinery_logo.png'),
        ]);
    }

    public function edit(LeaveTemplate $leave_template): Response
    {
        return Inertia::render('leave-templates/edit', [
            'template' => $this->payload($leave_template),
            'leaveTypes' => $this->leaveTypes(),
            'organisations' => $this->organisations(),
        ]);
    }

    public function update(
        UpdateLeaveTemplateRequest $request,
        LeaveTemplate $leave_template,
    ): RedirectResponse {
        $leave_template->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave template updated.'),
        ]);

        return to_route('leave-templates.show', $leave_template);
    }

    public function destroy(LeaveTemplate $leave_template): RedirectResponse
    {
        $leave_template->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave template deleted.'),
        ]);

        return to_route('leave-templates.index');
    }

    public function updateStatus(Request $request, LeaveTemplate $leave_template): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'integer', 'in:0,1']]);

        if ((int) $validated['status'] === 1) {
            $duplicateExists = LeaveTemplate::query()
                ->where('leave_type_id', $leave_template->leave_type_id)
                ->where('status', 1)
                ->where('id', '<>', $leave_template->id)
                ->when(
                    $leave_template->organisation_id === null,
                    fn ($query) => $query->whereNull('organisation_id'),
                    fn ($query) => $query->where('organisation_id', $leave_template->organisation_id),
                )
                ->exists();

            if ($duplicateExists) {
                return back()->withErrors([
                    'status' => 'An active template already exists for this leave type and organisation.',
                ]);
            }
        }

        $leave_template->update(['status' => (int) $validated['status']]);

        return back();
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function leaveTypes(): array
    {
        return LeaveType::query()
            ->orderBy('leave_name')
            ->get(['id', 'leave_name', 'code'])
            ->map(fn (LeaveType $type): array => [
                'id' => $type->id,
                'name' => $type->leave_name,
                'code' => $type->code,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, short_name: string|null}>
     */
    private function organisations(): array
    {
        return Organisation::query()
            ->where('status', 1)
            ->orderBy('org_name')
            ->get(['id', 'org_name', 'short_name'])
            ->map(fn (Organisation $organisation): array => [
                'id' => $organisation->id,
                'name' => $organisation->org_name,
                'short_name' => $organisation->short_name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveTemplate $template): array
    {
        return [
            'id' => $template->id,
            'leave_type_id' => $template->leave_type_id,
            'organisation_id' => $template->organisation_id,
            'template_name' => $template->template_name,
            'template_code' => $template->template_code,
            'status' => $template->status,
            'leave_type' => $template->leaveType
                ? [
                    'id' => $template->leaveType->id,
                    'name' => $template->leaveType->leave_name,
                    'code' => $template->leaveType->code,
                ]
                : null,
            'organisation' => $template->organisation
                ? [
                    'id' => $template->organisation->id,
                    'name' => $template->organisation->org_name,
                    'short_name' => $template->organisation->short_name,
                ]
                : null,
        ];
    }
}
