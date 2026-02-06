<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$users = User::all();
echo "Total users: " . $users->count() . "\n\n";
foreach ($users as $user) {
    $role = $user->role ? $user->role->name : 'N/A';
    echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}, Role: {$role}, Company ID: {$user->company_id}\n";
}
