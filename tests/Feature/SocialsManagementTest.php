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
