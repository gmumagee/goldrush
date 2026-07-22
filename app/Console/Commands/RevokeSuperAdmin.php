<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RevokeSuperAdmin extends Command
{
    protected $signature = 'admin:revoke-super-admin {email}';

    protected $description = 'Revoke super-admin access from the user with the given email address.';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', strtolower((string) $this->argument('email')))
            ->first();

        if (! $user) {
            $this->error('User not found for that email address.');

            return self::FAILURE;
        }

        if (! $user->isSuperAdmin()) {
            $this->info(sprintf('%s is not currently a super admin.', $user->email));

            return self::SUCCESS;
        }

        $user->forceFill(['is_super_admin' => false])->save();

        $this->info(sprintf('Revoked super-admin access from %s.', $user->email));

        return self::SUCCESS;
    }
}
