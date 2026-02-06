<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents basic users from accessing site-team index', function () {
    $basicRole = Role::firstOrCreate(['name' => 'basic']);
    $superRole = Role::firstOrCreate(['name' => 'super_admin']);

    $basicUser = User::factory()->create(['role_id' => $basicRole->id]);
    $superAdmin = User::factory()->create(['role_id' => $superRole->id]);

    $project = Project::create([
        'title' => 'Test Project',
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($basicUser, 'sanctum')
        ->getJson('/api/projects/' . $project->id . '/site-teams')
        ->assertStatus(403)
        ->assertJsonFragment(['message' => 'You do not have permission to view site teams']);
});

it('prevents basic users from accessing floorplan index', function () {
    $basicRole = Role::firstOrCreate(['name' => 'basic']);
    $superRole = Role::firstOrCreate(['name' => 'super_admin']);

    $basicUser = User::factory()->create(['role_id' => $basicRole->id]);
    $superAdmin = User::factory()->create(['role_id' => $superRole->id]);

    $project = Project::create([
        'title' => 'Test Project',
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($basicUser, 'sanctum')
        ->getJson('/api/projects/' . $project->id . '/floorplans')
        ->assertStatus(403)
        ->assertJsonFragment(['message' => 'You do not have permission to view floor plans']);
});

it('does not return floorPlans in project show for basic users', function () {
    $basicRole = Role::firstOrCreate(['name' => 'basic']);
    $superRole = Role::firstOrCreate(['name' => 'super_admin']);

    $basicUser = User::factory()->create(['role_id' => $basicRole->id]);
    $superAdmin = User::factory()->create(['role_id' => $superRole->id]);

    $project = Project::create([
        'title' => 'Test Project',
        'created_by' => $superAdmin->id,
    ]);

    // Create a floorplan record
    $project->floorPlans()->create([
        'title' => 'FP1',
        'file_path' => 'dummy.pdf',
        'original_filename' => 'dummy.pdf',
    ]);

    $this->actingAs($basicUser, 'sanctum')
        ->getJson('/api/projects/' . $project->id)
        ->assertStatus(200)
        ->assertJsonMissing(['floorPlans']);
});