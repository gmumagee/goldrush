<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantSuperAdmin extends Command
{
    protected $signature = 'admin:grant-super-admin {email}';

    protected $description = 'Grant super-admin access to the user with the given email address.';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', strtolower((string) $this->argument('email')))
            ->first();

        if (! $user) {
            $this->error('User not found for that email address.');

            return self::FAILURE;
        }

        if ($user->isSuperAdmin()) {
            $this->info(sprintf('%s is already a super admin.', $user->email));

            return self::SUCCESS;
        }

        $user->forceFill(['is_super_admin' => true])->save();

        $this->info(sprintf('Granted super-admin access to %s.', $user->email));

        return self::SUCCESS;
    }
}
