<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Invitation;
use App\Models\Project;

uses(RefreshDatabase::class);

it('allows manager without invitation but with company linked to see org projects', function () {
    // Create super admin and admin
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin.org@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    // Create company under super admin
    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);

    // Create projects by super and admin
    $project1 = Project::factory()->create(['created_by' => $super->id, 'title' => 'ProjectSuper']);
    $project2 = Project::factory()->create(['created_by' => $admin->id, 'title' => 'ProjectAdmin']);

    // Create manager WITHOUT an invitation, but assigned to the company
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    $this->actingAs($manager, 'sanctum');

    $resp = $this->getJson('/api/projects');
    $resp->assertStatus(200);

    $data = $resp->json();
    $titles = array_column($data, 'title');

    expect($titles)->toContain('ProjectSuper');
    expect($titles)->toContain('ProjectAdmin');
});

it('allows basic user assigned to company to see org projects', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin.basic@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);

    $project1 = Project::factory()->create(['created_by' => $super->id, 'title' => 'ProjectSuperB']);
    $project2 = Project::factory()->create(['created_by' => $admin->id, 'title' => 'ProjectAdminB']);

    $basic = User::factory()->create(['company_id' => $company->id]);
    $basic->role_id = \App\Models\Role::where('name', 'user')->first()->id;
    $basic->save();

    $this->actingAs($basic, 'sanctum');

    $resp = $this->getJson('/api/projects');
    $resp->assertStatus(200);

    $data = $resp->json();
    $titles = array_column($data, 'title');

    expect($titles)->toContain('ProjectSuperB');
    expect($titles)->toContain('ProjectAdminB');
});

it('allows subcontractor assigned to company to see org projects', function () {
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $adminEmail = 'admin.sub@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $company = \App\Models\Company::factory()->create(['created_by_user_id' => $super->id]);

    $project1 = Project::factory()->create(['created_by' => $super->id, 'title' => 'ProjectSuperS']);
    $project2 = Project::factory()->create(['created_by' => $admin->id, 'title' => 'ProjectAdminS']);

    $sub = User::factory()->create(['company_id' => $company->id]);
    $sub->role_id = \App\Models\Role::where('name', 'subcontractor')->first()->id;
    $sub->save();

    $this->actingAs($sub, 'sanctum');

    $resp = $this->getJson('/api/projects');
    $resp->assertStatus(200);

    $data = $resp->json();
    $titles = array_column($data, 'title');

    expect($titles)->toContain('ProjectSuperS');
    expect($titles)->toContain('ProjectAdminS');
});