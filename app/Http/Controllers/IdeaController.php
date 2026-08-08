<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IdeaController extends Controller
{
    public function index(Request $request)
    {
        $ideas = Idea::with('employee:id,name,employee_code,image_url')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('ideas/index', [
            'ideas' => $ideas,
        ]);
    }

    public function show(Idea $idea)
    {
        $idea->load([
            'employee:id,name,employee_code,designation,department_id,image_url',
            'employee.department:id,name',
            'tracks' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'tracks.doneBy:id,name',
        ]);

        return Inertia::render('ideas/show', [
            'idea' => $idea,
        ]);
    }

    public function updateStatus(Request $request, Idea $idea)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,accepted,approved,rejected,implemented',
            'remarks' => 'nullable|string',
            'review_comment' => 'nullable|string',
        ]);

        $idea->update([
            'status' => $validated['status'],
            'review_comment' => $validated['review_comment'] ?? $idea->review_comment,
        ]);

        $idea->tracks()->create([
            'action_type' => $validated['status'],
            'remarks' => $validated['remarks'],
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        return back()->with('success', 'Idea status updated successfully.');
    }
}
