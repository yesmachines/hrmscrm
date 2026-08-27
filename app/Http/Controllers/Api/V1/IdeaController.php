<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdeaController extends Controller
{
    /**
     * Get a listing of the user's ideas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $ideas = Idea::with('tracks')
            ->where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->successPaginatedResponse($ideas, 'ideas');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'idea_files' => 'nullable|array',
            'idea_files.*' => 'file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:10240',
        ]);

        $filePaths = [];
        if ($request->hasFile('idea_files')) {
            foreach ($request->file('idea_files') as $file) {
                $filePaths[] = $file->store('ideas', 'public');
            }
        }

        $idea = Idea::create([
            'employee_id' => $employee->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'idea_files' => $filePaths,
            'status' => 'submitted',
        ]);

        $idea->tracks()->create([
            'action_type' => 'submitted',
            'remarks' => 'Idea submitted',
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        return $this->successResponse([
            'idea' => $idea->load('tracks'),
        ], 'Idea submitted successfully.', 201);
    }
}
