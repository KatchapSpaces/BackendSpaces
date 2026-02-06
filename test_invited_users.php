<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

use App\Models\User;
use App\Models\Invitation;

// Get the super admin
$superAdmin = User::where('email', 'muhammadumarh23@gmail.com')->first();

if (!$superAdmin) {
    echo "Super admin not found\n";
    exit;
}

echo "=== TESTING SUPER ADMIN INVITED USERS VIEW ===\n\n";
echo "Super Admin: " . $superAdmin->email . "\n";
echo "Role: " . ($superAdmin->role ? $superAdmin->role->name : 'No role') . "\n\n";

// Get all active users
$users = User::with(['role', 'company'])->get();
echo "Active Users: " . $users->count() . "\n";
foreach ($users as $user) {
    echo "  - " . $user->email . " (" . ($user->role ? $user->role->name : 'No role') . ")\n";
}

echo "\n";

// Get all pending invitations
$invitations = Invitation::whereNull('accepted_at')->with('inviter')->get();
echo "Pending Invitations: " . $invitations->count() . "\n";
foreach ($invitations as $inv) {
    echo "  - " . $inv->email . " (Role: " . $inv->role . ", Invited by: " . ($inv->inviter ? $inv->inviter->email : 'Unknown') . ")\n";
}

echo "\n✅ SUCCESS: The invitations table is accessible!\n";
