<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountBackup;
use App\Models\PlanUpgradeRequest;
use App\Models\SuperAdminAuditLog;
use App\Models\User;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoResetService
{
    public function reset(): void
    {
        Demo::ensureEnabled('Demo reset refused because demo mode is disabled.');

        DB::transaction(function (): void {
            $demoAccount = Account::query()
                ->where('slug', Demo::accountSlug())
                ->first();

            $userIds = collect();

            if ($demoAccount) {
                $userIds = DB::table('tbl_account_users')
                    ->where('account_id', $demoAccount->id)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $backups = AccountBackup::query()
                    ->where('account_id', $demoAccount->id)
                    ->get();

                foreach ($backups as $backup) {
                    if ($backup->file_disk && $backup->file_path) {
                        Storage::disk($backup->file_disk)->delete($backup->file_path);
                    }
                }

                AccountBackup::query()
                    ->where('account_id', $demoAccount->id)
                    ->delete();

                PlanUpgradeRequest::query()
                    ->where('account_id', $demoAccount->id)
                    ->delete();

                DB::table('tbl_audit_log')
                    ->where('account_id', $demoAccount->id)
                    ->delete();

                DB::table('tbl_super_admin_audit_log')
                    ->where('account_id', $demoAccount->id)
                    ->delete();

                foreach ([
                    'tbl_service_sales',
                    'tbl_transactions',
                    'tbl_services',
                    'tbl_calendar_reminders',
                    'tbl_calendar_events',
                    'tbl_inventory_ledger',
                    'tbl_purchase_items',
                    'tbl_purchases',
                    'tbl_location_documents',
                    'tbl_location_contacts',
                    'tbl_route_locations',
                    'tbl_bins',
                    'tbl_machines',
                    'tbl_contacts',
                    'tbl_locations',
                    'tbl_routes',
                    'tbl_products',
                    'tbl_vendors',
                    'tbl_warehouses',
                    'tbl_account_users',
                    'tbl_data_dictionary',
                ] as $table) {
                    DB::table($table)
                        ->where('account_id', $demoAccount->id)
                        ->delete();
                }

                $demoAccount->delete();
            }

            $demoSharedUser = User::query()
                ->where('email', Demo::sharedUserEmail())
                ->first();

            if ($demoSharedUser) {
                $userIds->push((int) $demoSharedUser->id);
            }

            $userIds = $userIds
                ->filter(fn ($id) => (int) $id > 0)
                ->unique()
                ->values();

            if ($userIds->isNotEmpty()) {
                AccountBackup::query()
                    ->whereIn('requested_by_user_id', $userIds)
                    ->delete();

                PlanUpgradeRequest::query()
                    ->whereIn('requested_by_user_id', $userIds)
                    ->delete();

                DB::table('tbl_audit_log')
                    ->whereIn('user_id', $userIds)
                    ->delete();

                SuperAdminAuditLog::query()
                    ->whereIn('user_id', $userIds)
                    ->delete();

                DB::table('sessions')
                    ->whereIn('user_id', $userIds)
                    ->delete();

                User::query()
                    ->whereIn('id', $userIds)
                    ->delete();
            }
        });

        app(DemoSeeder::class)->run();
    }
}
