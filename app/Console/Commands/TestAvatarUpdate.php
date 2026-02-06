<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestAvatarUpdate extends Command
{
    protected $signature = 'test:avatar {userId=12}';
    protected $description = 'Test updating avatar for a user by copying an existing file and saving the user record';

    public function handle()
    {
        $userId = $this->argument('userId');
        $user = \App\Models\User::find($userId);

        if (!$user) {
            $this->error("User not found: $userId");
            return 1;
        }

        $sourceFiles = Storage::disk('public')->files('avatars');
        if (empty($sourceFiles)) {
            $this->error('No source avatars found in public disk under avatars/');
            return 1;
        }

        // Pick first existing avatar file
        $source = $sourceFiles[0];
        $this->info("Using source: $source");

        $dest = 'avatars/test-avatar-' . time() . '.webp';

        try {
            // Copy file within public disk
            $contents = Storage::disk('public')->get($source);
            Storage::disk('public')->put($dest, $contents);

            // Update user avatar path and save
            $user->avatar = $dest;
            $user->save();

            $this->info('Avatar updated successfully for user ' . $userId . ' to ' . $dest);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error updating avatar: ' . $e->getMessage());
            \Log::error('test:avatar failed', ['exception' => $e]);
            return 1;
        }
    }
}
