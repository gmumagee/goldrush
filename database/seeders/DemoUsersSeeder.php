<?php

namespace Database\Seeders;

use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends DemoSeeder
{
    public function run(): void
    {
        $demoAccount = $this->demoAccount();
        $otherAccount = $this->otherAccount();

        $demoUsers = [
            [
                'name' => 'Owner User',
                'email' => 'owner@example.com',
                'role' => AccountUser::ROLE_OWNER,
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => AccountUser::ROLE_ADMIN,
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'role' => AccountUser::ROLE_MANAGER,
            ],
            [
                'name' => 'Technician User',
                'email' => 'tech@example.com',
                'role' => AccountUser::ROLE_TECHNICIAN,
            ],
            [
                'name' => 'Viewer User',
                'email' => 'viewer@example.com',
                'role' => AccountUser::ROLE_VIEWER,
            ],
        ];

        foreach ($demoUsers as $definition) {
            $user = $this->upsertUser($definition['name'], $definition['email']);

            AccountUser::query()->updateOrCreate(
                [
                    'account_id' => $demoAccount->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $definition['role'],
                    'status' => AccountUser::STATUS_ACTIVE,
                ],
            );
        }

        $otherOwner = $this->upsertUser('Other Owner', 'owner@other.test');

        AccountUser::query()->updateOrCreate(
            [
                'account_id' => $otherAccount->id,
                'user_id' => $otherOwner->id,
            ],
            [
                'role' => AccountUser::ROLE_OWNER,
                'status' => AccountUser::STATUS_ACTIVE,
            ],
        );

        AccountUser::query()
            ->where('account_id', $demoAccount->id)
            ->where('user_id', $otherOwner->id)
            ->delete();
    }

    protected function upsertUser(string $name, string $email): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->name = $name;
        $user->status = User::STATUS_ACTIVE;
        $user->email_verified_at = now();

        if (! $user->exists || ! Hash::check('password', (string) $user->password)) {
            $user->password = Hash::make('password');
        }

        $user->save();

        return $user;
    }
}
