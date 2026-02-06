<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\SiteTeam;

uses(RefreshDatabase::class);

it('restricts manager to see only site teams they created', function () {
    // Create super admin
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    // Create admin invited by super
    $adminEmail = 'admin@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    // Create manager invited by admin
    $managerEmail = 'manager@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    // Create project by super
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Create site teams: created by super, admin, manager, and an outsider
    $u1 = User::factory()->create(['email' => 'team1@example.test']);
    $u2 = User::factory()->create(['email' => 'team2@example.test']);
    $u3 = User::factory()->create(['email' => 'team3@example.test']);
    $u4 = User::factory()->create(['email' => 'team4@example.test']);

    SiteTeam::create(['user_id' => $u1->id, 'project_id' => $project->id, 'role' => 'subcontractor', 'created_by' => $super->id]);
    SiteTeam::create(['user_id' => $u2->id, 'project_id' => $project->id, 'role' => 'basic', 'created_by' => $admin->id]);
    SiteTeam::create(['user_id' => $u3->id, 'project_id' => $project->id, 'role' => 'basic', 'created_by' => $manager->id]);
    // Created by some other user outside org
    $outsider = User::factory()->create();
    SiteTeam::create(['user_id' => $u4->id, 'project_id' => $project->id, 'role' => 'basic', 'created_by' => $outsider->id]);

    // Act as manager
    $this->actingAs($manager, 'sanctum');

    $resp = $this->getJson("/api/projects/{$project->id}/site-teams");
    $resp->assertStatus(200);

    $data = $resp->json();

    // Should contain only manager-created site team
    $emails = array_column($data, 'email');
    expect($emails)->toContain($u3->email);
    expect($emails)->not->toContain($u1->email);
    expect($emails)->not->toContain($u2->email);
    expect($emails)->not->toContain($u4->email);
});

it('prevents manager from creating site team members with disallowed roles', function () {
    // Create super/admin/manager
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin2@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $managerEmail = 'manager2@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();


    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Create invitation for target user
    Invitation::create(['email' => 't@example.test', 'role' => 'manager', 'invited_by' => $admin->id]);

    $this->actingAs($manager, 'sanctum');

    $resp = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => 'Target',
        'email' => 't@example.test',
        'role' => 'manager', // disallowed for manager
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp->assertStatus(403);
    $resp->assertJsonFragment(['message' => 'Managers can only create site team members with roles subcontractor or basic']);
});

it('allows manager to create subcontractor or basic site team members', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin3@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $managerEmail = 'manager3@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Target invitation (for subcontractor)
    Invitation::create(['email' => 't2@example.test', 'role' => 'subcontractor', 'invited_by' => $admin->id]);
    // Target invitation (for basic)
    Invitation::create(['email' => 't3@example.test', 'role' => 'basic', 'invited_by' => $admin->id]);

    $this->actingAs($manager, 'sanctum');

    // Create subcontractor
    $resp = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => 'Target',
        'email' => 't2@example.test',
        'role' => 'subcontractor',
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp->assertStatus(201);
    $resp->assertJsonFragment(['email' => 't2@example.test']);

    // Create basic
    $resp2 = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => 'TargetBasic',
        'email' => 't3@example.test',
        'role' => 'basic',
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp2->assertStatus(201);
    $resp2->assertJsonFragment(['email' => 't3@example.test']);
});

it('allows adding existing user with matching role without invitation', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin4@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $managerEmail = 'manager4@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Create an existing basic user WITHOUT an invitation
    $existingBasic = User::factory()->create(['email' => 'existing_basic@example.test', 'company_id' => $company->id]);
    $existingBasic->role_id = \App\Models\Role::where('name', 'basic')->first()->id;
    $existingBasic->save();

    $this->actingAs($manager, 'sanctum');

    $resp = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => $existingBasic->name,
        'email' => $existingBasic->email,
        'role' => 'basic',
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp->assertStatus(201);
    $resp->assertJsonFragment(['email' => $existingBasic->email]);
});

it('allows manager to create basic when invitation role is user (treat basic as user)', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin5@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $managerEmail = 'manager5@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Invitation exists but with legacy role 'user'
    Invitation::create(['email' => 't4@example.test', 'role' => 'user', 'invited_by' => $admin->id]);

    $this->actingAs($manager, 'sanctum');

    $resp = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => 'TargetUserLegacy',
        'email' => 't4@example.test',
        'role' => 'basic', // request basic should match invited 'user'
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp->assertStatus(201);
    $resp->assertJsonFragment(['email' => 't4@example.test']);
});

it('allows adding existing user with role user when requested role is basic', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin6@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $managerEmail = 'manager6@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);
    $project = Project::factory()->create(['created_by' => $super->id]);

    // Create an existing user with role 'user' (legacy) WITHOUT an invitation
    $existingUser = User::factory()->create(['email' => 'existing_user_legacy@example.test', 'company_id' => $company->id]);
    $existingUser->role_id = \App\Models\Role::where('name', 'user')->first()->id;
    $existingUser->save();

    $this->actingAs($manager, 'sanctum');

    $resp = $this->postJson("/api/projects/{$project->id}/site-teams", [
        'name' => $existingUser->name,
        'email' => $existingUser->email,
        'role' => 'basic',
        'company_id' => $company->id,
        'password' => 'secret123'
    ]);

    $resp->assertStatus(201);
    $resp->assertJsonFragment(['email' => $existingUser->email]);
});
