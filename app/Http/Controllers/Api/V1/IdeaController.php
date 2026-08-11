<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdeaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists(Employee::class, 'id'),
            ],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:10240',
        ]);

        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('ideas', 'public');
            }
        }

        $idea = Idea::create([
            'employee_id' => $validated['employee_id'],
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

        return response()->json([
            'message' => 'Idea submitted successfully.',
            'idea' => $idea->load('tracks'),
        ], 201);
    }
}
