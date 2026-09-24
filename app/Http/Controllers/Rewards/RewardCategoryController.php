<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RewardCategoryController extends Controller
{
    /**
     * Display a listing of reward categories.
     */
    public function index(): Response
    {
        $categories = RewardCategory::query()
            ->withCount('rewards')
            ->orderBy('reward_name')
            ->get();

        return Inertia::render('rewards/categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reward_name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:50', 'unique:reward_categories,short_code'],
            'status' => ['required', Rule::in([0, 1])],
            'details' => ['nullable', 'string'],
        ]);

        $validated['short_code'] = strtoupper(trim($validated['short_code']));

        RewardCategory::query()->create($validated);

        return redirect()->route('reward-categories.index')
            ->with('success', 'Reward category created successfully.');
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, RewardCategory $rewardCategory): RedirectResponse
    {
        $validated = $request->validate([
            'reward_name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:50', Rule::unique('reward_categories', 'short_code')->ignore($rewardCategory->id)],
            'status' => ['required', Rule::in([0, 1])],
            'details' => ['nullable', 'string'],
        ]);

        $validated['short_code'] = strtoupper(trim($validated['short_code']));

        $rewardCategory->update($validated);

        return redirect()->route('reward-categories.index')
            ->with('success', 'Reward category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(RewardCategory $rewardCategory): RedirectResponse
    {
        if ($rewardCategory->rewards()->exists()) {
            return back()->with('error', 'Cannot delete a reward category that has existing claims attached.');
        }

        $rewardCategory->delete();

        return redirect()->route('reward-categories.index')
            ->with('success', 'Reward category deleted successfully.');
    }
}
