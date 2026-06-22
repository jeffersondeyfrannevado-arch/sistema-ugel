<?php

namespace App\Support;

class AdminPermissions
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_SUB_ADMIN = 'sub_admin';
    public const ROLE_CUSTOM = 'custom';
    public const ROLE_USER = 'user';
    public const LEGACY_ROLE_ADMIN = 'admin';

    public const ALL = [
        'admin.dashboard.view',
        'admin.reports.export',
        'admin.users.view',
        'admin.users.create',
        'admin.users.update',
        'admin.users.suspend',
        'admin.users.delete',
        'admin.content.view',
        'admin.content.create',
        'admin.content.update',
        'admin.content.moderate',
        'admin.audit.view',
        'admin.audit.export',
        'admin.backups.view',
        'admin.backups.create',
        'admin.backups.restore',
        'admin.formats.manage',
    ];

    public static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            self::LEGACY_ROLE_ADMIN => self::ROLE_SUPER_ADMIN,
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SUB_ADMIN,
            self::ROLE_CUSTOM,
            self::ROLE_USER => $role,
            default => self::ROLE_USER,
        };
    }

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SUB_ADMIN,
            self::ROLE_CUSTOM,
            self::ROLE_USER,
        ];
    }

    public static function defaultsForRole(?string $role): array
    {
        return match (self::normalizeRole($role)) {
            self::ROLE_SUPER_ADMIN => self::ALL,
            self::ROLE_SUB_ADMIN => [
                'admin.dashboard.view',
                'admin.reports.export',
                'admin.users.view',
                'admin.users.create',
                'admin.users.update',
                'admin.users.suspend',
                'admin.content.view',
                'admin.content.create',
                'admin.content.update',
                'admin.content.moderate',
                'admin.audit.view',
                'admin.backups.view',
                'admin.backups.create',
                'admin.formats.manage',
            ],
            self::ROLE_CUSTOM => [],
            default => [],
        };
    }

    public static function isAdminRole(?string $role): bool
    {
        return self::normalizeRole($role) !== self::ROLE_USER;
    }
}
