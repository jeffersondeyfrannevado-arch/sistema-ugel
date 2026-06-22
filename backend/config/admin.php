<?php

return [
    'mfa' => [
        'code_length' => (int) env('ADMIN_MFA_CODE_LENGTH', 6),
        'expires_minutes' => (int) env('ADMIN_MFA_EXPIRES_MINUTES', 10),
        'required_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_MFA_REQUIRED_ROLES', 'super_admin,sub_admin'))
        ))),
    ],

    'security' => [
        'max_login_attempts' => (int) env('ADMIN_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('ADMIN_LOCKOUT_MINUTES', 15),
        'token_refresh_minutes_before_expiry' => (int) env('ADMIN_TOKEN_REFRESH_MINUTES_BEFORE_EXPIRY', 5),
    ],

    'alerts' => [
        'enabled' => (bool) env('ADMIN_ALERTS_ENABLED', true),
        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_ALERT_EMAILS', ''))
        ))),
        'slack_webhook' => env('ADMIN_SLACK_WEBHOOK_URL'),
        'log_channel' => env('ADMIN_ALERT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],

    'backup' => [
        'disk' => env('ADMIN_BACKUP_DISK', 'local'),
        'directory' => env('ADMIN_BACKUP_DIRECTORY', 'admin_backups'),
        'schedule' => env('ADMIN_BACKUP_SCHEDULE', '02:00'),
    ],
];
