<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesCrm\Employee;
use App\Models\SocialMedia;
use App\Models\SocialPost;
use App\Models\SocialPostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SocialController extends Controller
{
    /**
     * Get social feed (published posts).
     */
    public function index(Request $request): JsonResponse
    {
        $posts = SocialPost::with([
            'author:id,user_id,emp_num,designation',
            'author.user:id,name',
            'media',
            'reactions.employee.user:id,name',
        ])
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->successResponse($posts, 'Social feed fetched successfully.');
    }

    /**
     * Create a new social post.
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

        try {
            $validated = $request->validate([
                'content' => ['nullable', 'string'],
                'media' => ['nullable', 'array', 'max:4'], // Max 4 items per post
                'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'], // max 20MB
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        if (empty($validated['content']) && ! $request->hasFile('media')) {
            return $this->errorResponse('Post must contain content or media.', 422);
        }

        DB::beginTransaction();
        try {
            $post = SocialPost::create([
                'posted_by' => $employee->id,
                'content' => $validated['content'] ?? null,
                'status' => 'published',
            ]);

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $index => $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $fileType = in_array($extension, ['mp4', 'mov', 'avi']) ? 'video' : 'photo';
                    $path = $file->store('socials/media', 'public');

                    SocialMedia::create([
                        'post_id' => $post->id,
                        'file_type' => $fileType,
                        'file_path' => $path,
                        'priority' => $index,
                    ]);
                }
            }
            DB::commit();

            return $this->successResponse($post->load('media'), 'Post created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to create post: '.$e->getMessage(), 500);
        }
    }

    /**
     * Edit a social post (content only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $post = SocialPost::where('id', $id)
            ->where('posted_by', $employee->id)
            ->first();

        if (! $post) {
            return $this->errorResponse('Post not found or unauthorized.', 404);
        }

        try {
            $validated = $request->validate([
                'content' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $post->update([
            'content' => $validated['content'] ?? null,
        ]);

        return $this->successResponse($post, 'Post updated successfully.');
    }

    /**
     * Delete a social post.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $post = SocialPost::where('id', $id)
            ->where('posted_by', $employee->id)
            ->first();

        if (! $post) {
            return $this->errorResponse('Post not found or unauthorized.', 404);
        }

        $post->delete();

        return $this->successResponse(null, 'Post deleted successfully.');
    }

    /**
     * React to a social post.
     */
    public function react(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $post = SocialPost::where('status', 'published')->find($id);

        if (! $post) {
            return $this->errorResponse('Post not found.', 404);
        }

        try {
            $validated = $request->validate([
                'reaction' => ['required', Rule::in(['like', 'sad', 'angry', 'dislike', 'excited', 'clap', 'celebrate'])],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $reaction = SocialPostReaction::updateOrCreate(
            ['post_id' => $post->id, 'employee_id' => $employee->id],
            ['reactions' => $validated['reaction'], 'status' => 1]
        );

        return $this->successResponse($reaction, 'Reaction applied successfully.');
    }
}
