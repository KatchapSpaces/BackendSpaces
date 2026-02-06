<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

uses(RefreshDatabase::class);

it('admin-created users are not auto-verified and cannot login before verifying', function () {
    Notification::fake();

    // Create super admin
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $this->actingAs($super, 'sanctum');

    $email = 'newadminuser@example.test';
    $password = 'secret123';

    $roleId = \App\Models\Role::where('name', 'manager')->first()->id;

    $resp = $this->postJson('/api/users', [
        'name' => 'New User',
        'email' => $email,
        'password' => $password,
        'role_id' => $roleId,
    ]);

    $resp->assertStatus(201);

    $created = User::where('email', $email)->first();
    expect($created)->not->toBeNull();
    expect($created->email_verified_at)->toBeNull();

    // Attempt login - should be forbidden until email verified
    $loginResp = $this->postJson('/api/login', [
        'email' => $email,
        'password' => $password,
    ]);

    $loginResp->assertStatus(403);
    $loginResp->assertJsonFragment(['message' => 'Please verify your email address before logging in. Check your email for the verification link.']);
});

it('invited users created with a password are not auto-verified and cannot login before verifying', function () {
    Notification::fake();

    // Create super admin
    $super = User::factory()->create();
    $super->role_id = \App\Models\Role::where('name', 'super_admin')->first()->id;
    $super->save();

    $this->actingAs($super, 'sanctum');

    $email = 'inviteduser@example.test';
    $password = 'secret456';

    $resp = $this->postJson('/api/invite', [
        'email' => $email,
        'name' => 'Invited User',
        'password' => $password,
        'role' => 'manager'
    ]);

    $resp->assertStatus(200);
    $resp->assertJson(['user_created' => true]);

    $created = User::where('email', $email)->first();
    expect($created)->not->toBeNull();
    expect($created->email_verified_at)->toBeNull();

    // Attempt login - should be forbidden until email verified
    $loginResp = $this->postJson('/api/login', [
        'email' => $email,
        'password' => $password,
    ]);

    $loginResp->assertStatus(403);
    $loginResp->assertJsonFragment(['message' => 'Please verify your email address before logging in. Check your email for the verification link.']);
});