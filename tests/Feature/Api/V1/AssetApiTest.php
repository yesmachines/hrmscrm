<?php

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetRequest;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Laravel\Sanctum\Sanctum;

test('employee can list their assigned assets via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Software Engineer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Laptop',
        'shortcode' => 'LAP',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'MacBook Pro 16 M3 Max',
        'referenceno' => 'AST-MBP-001',
        'condition' => 'Excellent',
        'status' => 'Active',
    ]);

    AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'assigned_to' => $employee->id,
        'assigned_date' => now()->toDateString(),
        'note' => 'Excellent condition during assignment',
        'status' => 'Assigned',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.assets.index'));

    $response->assertStatus(200)
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.assets.0.asset_name', 'MacBook Pro 16 M3 Max')
        ->assertJsonPath('data.assets.0.referenceno', 'AST-MBP-001')
        ->assertJsonPath('data.assets.0.assigned_to.id', $employee->id);
});

test('employee can view asset details via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Graphic Designer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Monitor',
        'shortcode' => 'MON',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'Dell Ultrasharp 27 4K',
        'referenceno' => 'AST-MON-002',
        'condition' => 'Good',
        'status' => 'Active',
    ]);

    AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'assigned_to' => $employee->id,
        'assigned_date' => now()->toDateString(),
        'note' => 'Good condition with HDMI cable',
        'status' => 'Assigned',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.assets.show', $asset->id));

    $response->assertStatus(200)
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.asset.referenceno', 'AST-MON-002')
        ->assertJsonPath('data.asset.category', 'Monitor')
        ->assertJsonPath('data.asset.condition', 'Good');
});

test('employee can list asset categories via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Analyst',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    AssetCategory::query()->create([
        'category' => 'Keyboard',
        'shortcode' => 'KBD',
        'status' => 1,
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.assets.categories'));

    $response->assertStatus(200)
        ->assertJsonPath('statusCode', 200)
        ->assertJsonStructure(['data' => ['categories' => [['id', 'category', 'shortcode']]]]);
});

test('employee can submit an asset request via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Developer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Mouse',
        'shortcode' => 'MSE',
        'status' => 1,
    ]);

    Sanctum::actingAs($salesUser);

    $payload = [
        'request_type' => 'New',
        'category_id' => $category->id,
        'description' => 'Need an ergonomic wireless mouse for desktop workstation.',
        'priority' => 'Normal',
    ];

    $response = $this->postJson(route('api.v1.assets.submit-request'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.request.request_type', 'New')
        ->assertJsonPath('data.request.status', 'Pending');

    $assetRequest = AssetRequest::query()->where('requested_by', $employee->id)->first();
    expect($assetRequest)->not->toBeNull()
        ->and($assetRequest->request_type)->toBe('New')
        ->and($assetRequest->priority)->toBe('Normal')
        ->and($assetRequest->request_no)->toStartWith('AR-');
});

test('employee can view their submitted asset requests via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'QA Engineer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Webcam',
        'shortcode' => 'CAM',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'Logitech 4K Brio',
        'referenceno' => 'AST-CAM-001',
        'condition' => 'Needs Repair',
        'status' => 'Active',
    ]);

    AssetRequest::query()->create([
        'request_no' => 'REQ-TEST-001',
        'requested_by' => $employee->id,
        'request_type' => 'Repair',
        'asset_id' => $asset->id,
        'category_id' => $category->id,
        'description' => 'Camera sensor blurry.',
        'priority' => 'High',
        'status' => 'Pending',
        'requested_date' => now()->toDateString(),
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.assets.requests'));

    $response->assertStatus(200)
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.requests.0.request_no', 'REQ-TEST-001')
        ->assertJsonPath('data.requests.0.request_type', 'Repair');
});

test('admin can approve employee asset request via CRM', function () {
    $adminUser = createHrmsLoginUser('admin');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Support Rep',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Headset',
        'shortcode' => 'HDS',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'Jabra Evolve 65',
        'referenceno' => 'AST-HDS-001',
        'condition' => 'Damaged',
        'status' => 'Active',
    ]);

    $assetRequest = AssetRequest::query()->create([
        'request_no' => 'REQ-APPROVE-1',
        'requested_by' => $employee->id,
        'request_type' => 'Replacement',
        'asset_id' => $asset->id,
        'category_id' => $category->id,
        'description' => 'Broken headset microphone.',
        'priority' => 'High',
        'status' => 'Pending',
        'requested_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($adminUser)
        ->withoutVite()
        ->post(route('asset-requests.approve', $assetRequest->id), [
            'admin_notes' => 'Approved. Replacement ordered from supplier.',
        ]);

    $response->assertRedirect();

    $assetRequest->refresh();
    expect($assetRequest->status)->toBe('Approved')
        ->and($assetRequest->admin_notes)->toBe('Approved. Replacement ordered from supplier.')
        ->and($assetRequest->approved_by)->toBe($adminUser->id);
});

test('admin can reject employee asset request via CRM with reason', function () {
    $adminUser = createHrmsLoginUser('admin');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Accountant',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Display Stand',
        'shortcode' => 'STD',
        'status' => 1,
    ]);

    $assetRequest = AssetRequest::query()->create([
        'request_no' => 'REQ-REJECT-1',
        'requested_by' => $employee->id,
        'request_type' => 'New',
        'category_id' => $category->id,
        'description' => 'Requesting secondary monitor stand.',
        'priority' => 'Low',
        'status' => 'Pending',
        'requested_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($adminUser)
        ->withoutVite()
        ->post(route('asset-requests.reject', $assetRequest->id), [
            'rejection_reason' => 'Secondary stands currently restricted to developer roles.',
        ]);

    $response->assertRedirect();

    $assetRequest->refresh();
    expect($assetRequest->status)->toBe('Rejected')
        ->and($assetRequest->rejection_reason)->toBe('Secondary stands currently restricted to developer roles.');
});

test('employee can fetch their assigned assets for dropdown selection via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Developer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Laptop',
        'shortcode' => 'LAP',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'Dell Latitude 5540',
        'referenceno' => 'AST-DEL-001',
        'condition' => 'Good',
        'status' => 'Active',
    ]);

    AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'assigned_to' => $employee->id,
        'assigned_date' => now()->toDateString(),
        'status' => 'Assigned',
        'note' => 'Standard dev setup',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.assets.my-assigned'));

    $response->assertStatus(200)
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.assigned_assets.0.id', $asset->id)
        ->assertJsonPath('data.assigned_assets.0.asset_name', 'Dell Latitude 5540')
        ->assertJsonPath('data.assigned_assets.0.referenceno', 'AST-DEL-001');
});

test('employee cannot submit repair request for an unassigned asset', function () {
    $salesUser = SalesCrmUser::factory()->create();

    Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Support',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Keyboard',
        'shortcode' => 'KEY',
        'status' => 1,
    ]);

    // Asset that belongs to someone else
    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'Mechanical Keyboard',
        'referenceno' => 'AST-KEY-999',
        'condition' => 'Good',
        'status' => 'Active',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->postJson(route('api.v1.assets.submit-request'), [
        'request_type' => 'Repair',
        'asset_id' => $asset->id,
        'description' => 'Spacebar broken',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonPath('message', 'The selected asset is not currently assigned to you. Please select an asset from your assigned assets.');
});

test('employee can submit repair request for their assigned asset', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Lead Engineer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $category = AssetCategory::query()->create([
        'category' => 'Laptop',
        'shortcode' => 'LAP2',
        'status' => 1,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'asset_name' => 'MacBook Air M2',
        'referenceno' => 'AST-MBA-002',
        'condition' => 'Good',
        'status' => 'Active',
    ]);

    AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'assigned_to' => $employee->id,
        'assigned_date' => now()->toDateString(),
        'status' => 'Assigned',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->postJson(route('api.v1.assets.submit-request'), [
        'request_type' => 'Repair',
        'asset_id' => $asset->id,
        'description' => 'Battery draining abnormally fast.',
        'priority' => 'High',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.request.asset_id', $asset->id)
        ->assertJsonPath('data.request.category_id', $category->id)
        ->assertJsonPath('data.request.request_type', 'Repair');
});
