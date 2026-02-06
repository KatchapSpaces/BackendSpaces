<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateUserCompanies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-user-companies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate users with string-based company assignments to use proper company relationships';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting user company migration...');

        $users = \App\Models\User::whereNotNull('company')->whereNull('company_id')->get();

        if ($users->isEmpty()) {
            $this->info('No users need migration.');
            return;
        }

        $this->info("Found {$users->count()} users to migrate.");

        foreach($users as $user) {
            $company = \App\Models\Company::where('name', $user->company)->first();

            if (!$company) {
                $company = \App\Models\Company::create([
                    'name' => $user->company,
                    'email' => $user->email,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
                $this->info("Created new company: {$company->name}");
            }

            $user->company_id = $company->id;
            $user->save();

            $this->info("Migrated user {$user->email} to company {$company->name}");
        }

        $this->info('Migration completed successfully!');
    }
}
