<?php
// Script to remove collaboration permission from manager role

// Load Laravel components
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

try {
    // Get manager role
    $managerRole = Role::where('name', 'manager')->first();

    if (!$managerRole) {
        echo "❌ Manager role not found!\n";
        exit;
    }

    // Get collaboration permission
    $collaboration = Permission::where('name', 'collaboration')->first();

    if (!$collaboration) {
        echo "❌ Collaboration permission not found!\n";
        exit;
    }

    // Detach collaboration from manager role
    $managerRole->permissions()->detach($collaboration->id);

    echo "✅ Removed collaboration permission from manager role\n";

    // Show remaining permissions
    $permissions = $managerRole->permissions()->pluck('name')->toArray();
    echo "\nManager now has " . count($permissions) . " permissions:\n";
    foreach ($permissions as $perm) {
        echo "  • " . $perm . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

