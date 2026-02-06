<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== ALL USERS ENDPOINT RESPONSE ===\n\n";

$users = User::with(['role', 'company'])
    ->select('id', 'name', 'email', 'role_id', 'company_id', 'status', 'created_at')
    ->orderBy('created_at', 'desc')
    ->get()
    ->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
            ] : null,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'status' => $user->company->status,
            ] : null,
        ];
    });

echo json_encode($users, JSON_PRETTY_PRINT);
