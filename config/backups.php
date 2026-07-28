<?php

return [
    'disk' => env('ACCOUNT_BACKUP_DISK', 'private'),
    'directory' => env('ACCOUNT_BACKUP_DIRECTORY', 'account-backups'),
    'retention_days' => (int) env('ACCOUNT_BACKUP_RETENTION_DAYS', 90),
];
