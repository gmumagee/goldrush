<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_account_users')->whereRaw('LOWER(role) = ?', ['owner'])->update(['role' => 'Owner']);
        DB::table('tbl_account_users')->whereRaw('LOWER(role) = ?', ['admin'])->update(['role' => 'Admin']);
        DB::table('tbl_account_users')->whereRaw('LOWER(role) = ?', ['manager'])->update(['role' => 'Manager']);
        DB::table('tbl_account_users')->whereRaw('LOWER(role) = ?', ['technician'])->update(['role' => 'Technician']);
        DB::table('tbl_account_users')->whereRaw('LOWER(role) = ?', ['viewer'])->update(['role' => 'Viewer']);
    }

    public function down(): void
    {
        DB::table('tbl_account_users')->where('role', 'Owner')->update(['role' => 'owner']);
        DB::table('tbl_account_users')->where('role', 'Admin')->update(['role' => 'admin']);
        DB::table('tbl_account_users')->where('role', 'Manager')->update(['role' => 'manager']);
        DB::table('tbl_account_users')->where('role', 'Technician')->update(['role' => 'technician']);
        DB::table('tbl_account_users')->where('role', 'Viewer')->update(['role' => 'viewer']);
    }
};
