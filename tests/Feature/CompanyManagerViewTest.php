<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Invitation;
use App\Models\Company;

uses(RefreshDatabase::class);

it('allows manager to see companies from their organization', function () {
    // Super admin
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    // Admin invited by super
    $adminEmail = 'admin@example.test';
    Invitation::create(['email' => $adminEmail, 'role' => 'admin', 'invited_by' => $super->id]);
    $admin = User::factory()->create(['email' => $adminEmail]);
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    // Manager invited by admin
    $managerEmail = 'manager@example.test';
    Invitation::create(['email' => $managerEmail, 'role' => 'manager', 'invited_by' => $admin->id]);
    $manager = User::factory()->create(['email' => $managerEmail]);
    $manager->role_id = \App\Models\Role::where('name', 'manager')->first()->id;
    $manager->save();

    // Super admin's companies
    $c1 = Company::factory()->create(['created_by_user_id' => $super->id, 'name' => 'C1']);
    $c2 = Company::factory()->create(['created_by_user_id' => $super->id, 'name' => 'C2']);

    $this->actingAs($manager, 'sanctum');

    $resp = $this->getJson('/api/companies');
    $resp->assertStatus(200);

    $data = $resp->json('companies');
    $names = array_map(fn($c) => $c['name'], $data);

    expect($names)->toContain('C1');
    expect($names)->toContain('C2');
});