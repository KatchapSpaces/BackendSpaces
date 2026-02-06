<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('users')->where('email', 'mumarh135@gmail.com')->update(['role' => 'admin']);
    }

    public function down()
    {
        // no-op: do not revert role change automatically
    }
};
