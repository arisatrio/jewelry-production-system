<?php

namespace App\Support;

use App\Models\User;

/**
 * Gate SPK approval berdasarkan permission di tabel mahakarya
 * (role → role_permissions → permissions), bukan kolom spk_role.
 */
class SpkApprovalRoles
{
    public static function can(?User $user, string $permission): bool
    {
        if ($user !== null) {
            return $user->hasPermission($permission);
        }

        /** @var list<string> $guest */
        $guest = config('spk.approval.guest_permissions', []);

        return in_array($permission, $guest, true);
    }

    public static function canView(?User $user): bool
    {
        return self::can($user, SpkPermissions::VIEW);
    }

    public static function canCreate(?User $user): bool
    {
        return self::can($user, SpkPermissions::CREATE);
    }

    public static function canEditDraft(?User $user): bool
    {
        return self::can($user, SpkPermissions::EDIT_DRAFT);
    }

    public static function canSubmit(?User $user): bool
    {
        return self::can($user, SpkPermissions::SUBMIT);
    }

    public static function canApprove(?User $user): bool
    {
        return self::can($user, SpkPermissions::APPROVE);
    }

    public static function canReject(?User $user): bool
    {
        return self::can($user, SpkPermissions::REJECT);
    }

    public static function canDecide(?User $user): bool
    {
        return self::canApprove($user) || self::canReject($user);
    }

    /**
     * Label peran untuk UI / Inertia (nama role, atau guest).
     */
    public static function roleLabel(?User $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        $name = $user->role?->name;

        return is_string($name) && $name !== '' ? $name : 'user';
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(?User $user): array
    {
        if ($user !== null) {
            return $user->permissionNames();
        }

        /** @var list<string> $guest */
        $guest = config('spk.approval.guest_permissions', []);

        return array_values(array_intersect($guest, SpkPermissions::all()));
    }
}
