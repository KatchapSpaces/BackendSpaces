<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\Invitation;

$superAdminId = 11; // change if needed
$authUser = User::find($superAdminId);
$roles = Role::with('permissions')->get();
$out = [];
foreach ($roles as $r) {
    $userCount = $r->users()->count();
    if ($authUser && $authUser->role && $authUser->role->name === 'super_admin') {
        $companyIds = Company::where('created_by_user_id', $authUser->id)->pluck('id')->toArray();
        $accepted = User::where('role_id', $r->id)->whereIn('company_id', $companyIds)->count();
        $pending = Invitation::where('role', $r->name)->where('invited_by', $authUser->id)->whereNull('accepted_at')->count();
        $userCount = $accepted + $pending;
    }
    $out[] = [
        'id' => $r->id,
        'name' => $r->name,
        'user_count' => $userCount,
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
