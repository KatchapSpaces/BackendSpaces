<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up()
    {
        $email = 'mumarh135@gmail.com';

        $user = DB::table('users')->where('email', $email)->first();
        if ($user) {
            DB::table('users')->where('email', $email)->update([
                'password' => Hash::make($email),
                'role' => 'admin',
                'email_verified_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } else {
            DB::table('users')->insert([
                'name' => null,
                'email' => $email,
                'password' => Hash::make($email),
                'company' => null,
                'email_verified_at' => Carbon::now(),
                'role' => 'admin',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down()
    {
        // Intentionally left empty: do not revert admin password changes automatically
    }
};
