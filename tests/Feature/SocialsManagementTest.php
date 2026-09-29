<?php

use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use App\Models\SocialPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    // Create user and employee for authentication
    $this->user = User::factory()->create();
    $this->employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'emp_num' => uniqid('TSOC-'),
        'first_name' => 'Test',
        'last_name' => 'User',
        'designation' => 'Developer',
        'joining_date' => now()->toDateString(),
        'division' => 1,
        'location' => 1,
        'department' => 1,
        'active' => 1,
    ]);
});

test('employee can view social feed via api', function () {
    Sanctum::actingAs($this->user);

    SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'Hello World!',
        'status' => 'published',
    ]);

    $response = $this->getJson(route('api.v1.socials.index'));

    $response->assertStatus(200)
        ->assertJsonPath('data.data.0.content', 'Hello World!');
});

test('employee can create a text post via api', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson(route('api.v1.socials.store'), [
        'content' => 'My first post!',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('social_posts', [
        'posted_by' => $this->employee->id,
        'content' => 'My first post!',
    ]);
});

test('employee can create a post with photo media via api', function () {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('photo.jpg');

    $response = $this->postJson(route('api.v1.socials.store'), [
        'content' => 'Look at this photo',
        'media' => [$file],
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('social_media', [
        'file_type' => 'photo',
    ]);
});

test('employee can react to a post via api', function () {
    Sanctum::actingAs($this->user);

    $post = SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'I passed the test!',
        'status' => 'published',
    ]);

    $response = $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'clap',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('social_post_reactions', [
        'post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'reactions' => 'clap',
    ]);
});

test('employee can toggle/remove reaction by clicking the same reaction again', function () {
    Sanctum::actingAs($this->user);

    $post = SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'Testing reaction toggle',
        'status' => 'published',
    ]);

    // First click: add reaction
    $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'like',
    ])->assertStatus(200);

    $this->assertDatabaseHas('social_post_reactions', [
        'post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'reactions' => 'like',
    ]);

    // Second click: same reaction toggles it off
    $response = $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'like',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Reaction removed successfully.');

    $this->assertDatabaseMissing('social_post_reactions', [
        'post_id' => $post->id,
        'employee_id' => $this->employee->id,
    ]);
});

test('employee can change existing reaction to a different one', function () {
    Sanctum::actingAs($this->user);

    $post = SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'Testing reaction update',
        'status' => 'published',
    ]);

    $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'like',
    ])->assertStatus(200);

    $response = $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'celebrate',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.reactions', 'celebrate');

    $this->assertDatabaseHas('social_post_reactions', [
        'post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'reactions' => 'celebrate',
    ]);
});

test('employee can remove reaction via unreact delete route', function () {
    Sanctum::actingAs($this->user);

    $post = SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'Testing explicit delete reaction',
        'status' => 'published',
    ]);

    $this->postJson(route('api.v1.socials.react', $post->id), [
        'reaction' => 'sad',
    ])->assertStatus(200);

    $response = $this->deleteJson(route('api.v1.socials.react.destroy', $post->id));

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Reaction removed successfully.');

    $this->assertDatabaseMissing('social_post_reactions', [
        'post_id' => $post->id,
        'employee_id' => $this->employee->id,
    ]);
});

test('author can update post content and remove specific media', function () {
    Sanctum::actingAs($this->user);

    $file1 = UploadedFile::fake()->image('pic1.jpg');
    $file2 = UploadedFile::fake()->image('pic2.jpg');

    $createResponse = $this->postJson(route('api.v1.socials.store'), [
        'content' => 'Initial post text',
        'media' => [$file1, $file2],
    ]);

    $postId = $createResponse->json('data.id');
    $mediaItems = $createResponse->json('data.media');
    $mediaIdToRemove = $mediaItems[0]['id'];
    $mediaIdToKeep = $mediaItems[1]['id'];

    $updateResponse = $this->putJson(route('api.v1.socials.update', $postId), [
        'content' => 'Updated post text',
        'remove_media_ids' => [$mediaIdToRemove],
    ]);

    $updateResponse->assertStatus(200);
    $this->assertDatabaseHas('social_posts', [
        'id' => $postId,
        'content' => 'Updated post text',
    ]);
    $this->assertDatabaseMissing('social_media', [
        'id' => $mediaIdToRemove,
    ]);
    $this->assertDatabaseHas('social_media', [
        'id' => $mediaIdToKeep,
    ]);
});

test('author can remove all media from post if text content remains', function () {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('pic.jpg');

    $createResponse = $this->postJson(route('api.v1.socials.store'), [
        'content' => 'Post with media',
        'media' => [$file],
    ]);

    $postId = $createResponse->json('data.id');
    $mediaId = $createResponse->json('data.media.0.id');

    $updateResponse = $this->putJson(route('api.v1.socials.update', $postId), [
        'remove_all_media' => true,
    ]);

    $updateResponse->assertStatus(200);
    $this->assertDatabaseMissing('social_media', [
        'id' => $mediaId,
    ]);
    $this->assertDatabaseHas('social_posts', [
        'id' => $postId,
        'content' => 'Post with media',
    ]);
});

test('cannot remove all media if post has no content', function () {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('pic.jpg');

    $createResponse = $this->postJson(route('api.v1.socials.store'), [
        'media' => [$file],
    ]);

    $postId = $createResponse->json('data.id');

    $updateResponse = $this->putJson(route('api.v1.socials.update', $postId), [
        'remove_all_media' => true,
    ]);

    $updateResponse->assertStatus(422)
        ->assertJsonPath('message', 'Post must contain content or media.');
});

test('author can delete single media via media destroy route', function () {
    Sanctum::actingAs($this->user);

    $file1 = UploadedFile::fake()->image('pic1.jpg');
    $file2 = UploadedFile::fake()->image('pic2.jpg');

    $createResponse = $this->postJson(route('api.v1.socials.store'), [
        'content' => 'Two photos post',
        'media' => [$file1, $file2],
    ]);

    $postId = $createResponse->json('data.id');
    $mediaId = $createResponse->json('data.media.0.id');

    $deleteResponse = $this->deleteJson(route('api.v1.socials.media.destroy', ['post' => $postId, 'media' => $mediaId]));

    $deleteResponse->assertStatus(200);
    $this->assertDatabaseMissing('social_media', [
        'id' => $mediaId,
    ]);
});

test('hr can view moderation page', function () {
    $hr = createHrmsLoginUser('hr');
    $this->actingAs($hr);

    $response = $this->get(route('socials.moderation'));
    $response->assertOk();
});

test('hr can remove inappropriate post', function () {
    $hr = createHrmsLoginUser('hr');
    $this->actingAs($hr);

    $post = SocialPost::create([
        'posted_by' => $this->employee->id,
        'content' => 'Inappropriate content',
        'status' => 'published',
    ]);

    $response = $this->post(route('socials.update-status', $post->id), [
        'status' => 'removed',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('social_posts', [
        'id' => $post->id,
        'status' => 'removed',
    ]);
});
