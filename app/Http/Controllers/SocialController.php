<?php

namespace App\Http\Controllers;

use App\Models\SocialPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialController extends Controller
{
    /**
     * Display the moderation page for HR/Admins.
     */
    public function moderation(Request $request): Response
    {
        $posts = SocialPost::with([
            'author.user:id,name',
            'media',
        ])
            ->withCount('reactions')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('socials/moderation', [
            'posts' => $posts,
        ]);
    }

    /**
     * Update post status (e.g., mark as removed/hidden by Admin).
     */
    public function updateStatus(Request $request, SocialPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:published,hidden,removed,pending'],
        ]);

        $post->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', "Post status updated to {$validated['status']}.");
    }
}
