<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company, App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== DELETING ALL COMPANIES (WITH FK SAFETY) ===\n\n";

// First, set all users' company_id to NULL
User::query()->update(['company_id' => null]);
echo "✓ Cleared all users' company_id\n";

// Now delete companies
$count = Company::count();
Company::query()->delete();
echo "✓ Deleted {$count} companies\n";

echo "\nVerification:\n";
echo "  Total companies: " . Company::count() . "\n";
echo "  Total users: " . User::count() . "\n";

echo "\nDone!\n";
