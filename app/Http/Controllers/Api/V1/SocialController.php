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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use OpenApi\Attributes as OA;

class SocialController extends Controller
{
    /**
     * Get social feed (published posts).
     */
    #[OA\Get(
        path: '/api/v1/socials',
        summary: 'Get social media feed',
        security: [['sanctum' => []]],
        tags: ['Social Media'],
        responses: [
            new OA\Response(response: 200, description: 'Social feed retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/socials',
        summary: 'Create a new social post',
        security: [['sanctum' => []]],
        tags: ['Social Media'],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'content', type: 'string'),
                        new OA\Property(property: 'media', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Social post created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
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
     * Edit a social post (content and/or media management).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $post = SocialPost::where('id', $id)
            ->where('posted_by', $employee->id)
            ->first();

        if (! $post) {
            return $this->errorResponse('Post not found or unauthorized.', 404);
        }

        try {
            $validated = $request->validate([
                'content' => ['nullable', 'string'],
                'remove_all_media' => ['nullable'],
                'remove_media_ids' => ['nullable'],
                'deleted_media_ids' => ['nullable'],
                'remove_media' => ['nullable'],
                'media' => ['nullable', 'array', 'max:4'],
                'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $removeAllMedia = $request->boolean('remove_all_media');
        if (! $removeAllMedia && $request->has('remove_media')) {
            $val = $request->input('remove_media');
            if (is_bool($val) && $val) {
                $removeAllMedia = true;
            } elseif (is_string($val) && in_array(strtolower($val), ['true', '1', 'all'])) {
                $removeAllMedia = true;
            }
        }

        $mediaIdsToRemove = [];
        if (! $removeAllMedia) {
            $candidates = [];
            if ($request->has('remove_media_ids')) {
                $candidates[] = $request->input('remove_media_ids');
            }
            if ($request->has('deleted_media_ids')) {
                $candidates[] = $request->input('deleted_media_ids');
            }
            if ($request->has('remove_media')) {
                $candidates[] = $request->input('remove_media');
            }

            foreach ($candidates as $item) {
                if (is_string($item)) {
                    $exploded = explode(',', $item);
                    $mediaIdsToRemove = array_merge($mediaIdsToRemove, $exploded);
                } elseif (is_array($item)) {
                    $mediaIdsToRemove = array_merge($mediaIdsToRemove, $item);
                } elseif (is_numeric($item)) {
                    $mediaIdsToRemove[] = $item;
                }
            }

            $mediaIdsToRemove = array_values(array_unique(array_filter(array_map('intval', $mediaIdsToRemove))));
        }

        // Determine what the target content would be
        $targetContent = $request->has('content') ? $validated['content'] : $post->content;

        // Calculate media counts
        $remainingMediaCount = $removeAllMedia
            ? 0
            : (! empty($mediaIdsToRemove)
                ? $post->media()->whereNotIn('id', $mediaIdsToRemove)->count()
                : $post->media()->count());

        $newMediaCount = $request->hasFile('media') ? count($request->file('media')) : 0;
        $totalMediaCount = $remainingMediaCount + $newMediaCount;

        if ($totalMediaCount > 4) {
            return $this->errorResponse('Post cannot exceed a maximum of 4 media attachments.', 422);
        }

        if (empty(trim((string) $targetContent)) && $totalMediaCount === 0) {
            return $this->errorResponse('Post must contain content or media.', 422);
        }

        DB::beginTransaction();
        try {
            // Delete media marked for removal
            if ($removeAllMedia) {
                $toDelete = $post->media()->get();
            } elseif (! empty($mediaIdsToRemove)) {
                $toDelete = $post->media()->whereIn('id', $mediaIdsToRemove)->get();
            } else {
                $toDelete = collect();
            }

            foreach ($toDelete as $mediaItem) {
                $filePath = $mediaItem->getRawOriginal('file_path');
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }
                $thumbPath = $mediaItem->getRawOriginal('thumbnail_path');
                if ($thumbPath) {
                    Storage::disk('public')->delete($thumbPath);
                }
                $mediaItem->delete();
            }

            // Upload new media files if provided
            if ($request->hasFile('media')) {
                $existingCount = $post->media()->count();
                foreach ($request->file('media') as $index => $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $fileType = in_array($extension, ['mp4', 'mov', 'avi']) ? 'video' : 'photo';
                    $path = $file->store('socials/media', 'public');

                    SocialMedia::create([
                        'post_id' => $post->id,
                        'file_type' => $fileType,
                        'file_path' => $path,
                        'priority' => $existingCount + $index,
                    ]);
                }
            }

            // Update content if provided
            if ($request->has('content')) {
                $post->content = $validated['content'] ?? null;
            }
            $post->save();

            // Re-order remaining priorities
            $remaining = $post->media()->orderBy('priority')->get();
            foreach ($remaining as $idx => $m) {
                if ($m->priority !== $idx) {
                    $m->update(['priority' => $idx]);
                }
            }

            DB::commit();

            return $this->successResponse(
                $post->load([
                    'media',
                    'author:id,user_id,emp_num,designation',
                    'author.user:id,name',
                    'reactions.employee.user:id,name',
                ]),
                'Post updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to update post: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete a single media attachment from a post.
     */
    public function destroyMedia(Request $request, $postId, $mediaId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $post = SocialPost::where('id', $postId)
            ->where('posted_by', $employee->id)
            ->first();

        if (! $post) {
            return $this->errorResponse('Post not found or unauthorized.', 404);
        }

        $media = SocialMedia::where('id', $mediaId)
            ->where('post_id', $post->id)
            ->first();

        if (! $media) {
            return $this->errorResponse('Media attachment not found on this post.', 404);
        }

        if (empty(trim((string) $post->content)) && $post->media()->count() <= 1) {
            return $this->errorResponse('Cannot remove media because post has no text content. Post must contain content or media.', 422);
        }

        $filePath = $media->getRawOriginal('file_path');
        if ($filePath) {
            Storage::disk('public')->delete($filePath);
        }
        $thumbPath = $media->getRawOriginal('thumbnail_path');
        if ($thumbPath) {
            Storage::disk('public')->delete($thumbPath);
        }
        $media->delete();

        // Re-order remaining priorities
        $remaining = $post->media()->orderBy('priority')->get();
        foreach ($remaining as $idx => $m) {
            if ($m->priority !== $idx) {
                $m->update(['priority' => $idx]);
            }
        }

        return $this->successResponse(null, 'Media removed successfully.');
    }

    /**
     * Delete a social post.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $post = SocialPost::where('id', $id)
            ->where('posted_by', $employee->id)
            ->first();

        if (! $post) {
            return $this->errorResponse('Post not found or unauthorized.', 404);
        }

        foreach ($post->media as $media) {
            $raw = $media->getRawOriginal('file_path');
            if ($raw) {
                Storage::disk('public')->delete($raw);
            }
            $rawThumb = $media->getRawOriginal('thumbnail_path');
            if ($rawThumb) {
                Storage::disk('public')->delete($rawThumb);
            }
        }

        $post->delete();

        return $this->successResponse(null, 'Post deleted successfully.');
    }

    /**
     * React to a social post (or remove reaction if clicked again).
     */
    public function react(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $post = SocialPost::where('status', 'published')->find($id);

        if (! $post) {
            return $this->errorResponse('Post not found.', 404);
        }

        // Allow explicit remove action
        if ($request->input('action') === 'remove' || $request->boolean('remove')) {
            $existingReaction = SocialPostReaction::where('post_id', $post->id)
                ->where('employee_id', $employee->id)
                ->first();

            if ($existingReaction) {
                $existingReaction->delete();
            }

            return $this->successResponse(null, 'Reaction removed successfully.');
        }

        try {
            $validated = $request->validate([
                'reaction' => ['nullable', Rule::in(['like', 'sad', 'angry', 'dislike', 'excited', 'clap', 'celebrate'])],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $existingReaction = SocialPostReaction::where('post_id', $post->id)
            ->where('employee_id', $employee->id)
            ->first();

        // If reaction is omitted or null, remove existing reaction if present
        if (empty($validated['reaction'])) {
            if ($existingReaction) {
                $existingReaction->delete();

                return $this->successResponse(null, 'Reaction removed successfully.');
            }

            return $this->errorResponse('Reaction is required.', 422);
        }

        // If clicked again with the same reaction, remove the reaction (toggle)
        if ($existingReaction && $existingReaction->reactions === $validated['reaction']) {
            $existingReaction->delete();

            return $this->successResponse(null, 'Reaction removed successfully.');
        }

        // If existing reaction exists but with a different reaction, update it
        if ($existingReaction) {
            $existingReaction->update([
                'reactions' => $validated['reaction'],
                'status' => 1,
            ]);

            return $this->successResponse($existingReaction->fresh(), 'Reaction updated successfully.');
        }

        // Otherwise create new reaction
        $reaction = SocialPostReaction::create([
            'post_id' => $post->id,
            'employee_id' => $employee->id,
            'reactions' => $validated['reaction'],
            'status' => 1,
        ]);

        return $this->successResponse($reaction, 'Reaction applied successfully.');
    }

    /**
     * Remove reaction from a social post.
     */
    public function unreact(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $post = SocialPost::where('status', 'published')->find($id);
        if (! $post) {
            return $this->errorResponse('Post not found.', 404);
        }

        $existing = SocialPostReaction::where('post_id', $post->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing) {
            $existing->delete();
        }

        return $this->successResponse(null, 'Reaction removed successfully.');
    }
}
