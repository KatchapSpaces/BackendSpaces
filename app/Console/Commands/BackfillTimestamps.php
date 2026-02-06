<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillTimestamps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-timestamps {--dry-run : Do a dry run and report counts without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing created_at timestamps for users and invitations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dry = $this->option('dry-run');

        $this->info('Scanning for missing timestamps...');

        // Users with missing or zero timestamps
        $usersMissing = DB::table('users')
            ->whereNull('created_at')
            ->orWhere('created_at', '0000-00-00 00:00:00')
            ->count();

        $this->info("Users missing created_at: {$usersMissing}");

        if ($usersMissing > 0) {
            if ($dry) {
                $this->info('Dry run: no user rows will be updated.');
            } else {
                DB::beginTransaction();
                try {
                    // Prefer existing updated_at or email_verified_at, fallback to NOW()
                    DB::table('users')
                        ->whereNull('created_at')
                        ->orWhere('created_at', '0000-00-00 00:00:00')
                        ->update(['created_at' => DB::raw("COALESCE(updated_at, email_verified_at, NOW())")]);

                    DB::commit();
                    $this->info("Updated {$usersMissing} users' created_at values.");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error('Failed to update users: ' . $e->getMessage());
                    Log::error('BackfillTimestamps users update failed: ' . $e->getMessage());
                }
            }
        }

        // Invitations with missing timestamps
        $invitationsMissing = DB::table('invitations')
            ->whereNull('created_at')
            ->orWhere('created_at', '0000-00-00 00:00:00')
            ->count();

        $this->info("Invitations missing created_at: {$invitationsMissing}");

        if ($invitationsMissing > 0) {
            if ($dry) {
                $this->info('Dry run: no invitation rows will be updated.');
            } else {
                DB::beginTransaction();
                try {
                    DB::table('invitations')
                        ->whereNull('created_at')
                        ->orWhere('created_at', '0000-00-00 00:00:00')
                        ->update(['created_at' => DB::raw("COALESCE(updated_at, NOW())")]);

                    DB::commit();
                    $this->info("Updated {$invitationsMissing} invitations' created_at values.");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error('Failed to update invitations: ' . $e->getMessage());
                    Log::error('BackfillTimestamps invitations update failed: ' . $e->getMessage());
                }
            }
        }

        $this->info('Backfill complete.');
        return 0;
    }
}
