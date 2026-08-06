<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SalesCrmRoles
{
    /**
     * Roles allowed to sign in to HRMS CRM (from salescrm.cm_roles).
     *
     * @var list<string>
     */
    public const LOGIN_ROLES = ['admin', 'hr'];

    /**
     * Spatie model_type stored in salescrm.cm_model_has_roles.
     */
    public const MODEL_TYPE = 'App\\Models\\User';

    /**
     * @return list<string>
     */
    public static function loginRoles(): array
    {
        return self::LOGIN_ROLES;
    }

    public static function userHasLoginAccess(int $userId): bool
    {
        return self::userHasAnyRole($userId, self::LOGIN_ROLES);
    }

    /**
     * @param  list<string>  $roleNames
     */
    public static function userHasAnyRole(int $userId, array $roleNames): bool
    {
        if ($roleNames === []) {
            return false;
        }

        return DB::connection('salescrm')
            ->table('model_has_roles as mhr')
            ->join('roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', self::MODEL_TYPE)
            ->where('mhr.model_id', $userId)
            ->whereIn('roles.name', $roleNames)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public static function roleNamesForUser(int $userId): array
    {
        return DB::connection('salescrm')
            ->table('model_has_roles as mhr')
            ->join('roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', self::MODEL_TYPE)
            ->where('mhr.model_id', $userId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * All Sales CRM roles available for employee ACL assignment (excludes superadmin id 1).
     *
     * @return list<array{name: string}>
     */
    public static function employeeRoleOptions(): array
    {
        return DB::connection('salescrm')
            ->table('roles')
            ->where('id', '<>', 1)
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($role): array => [
                'name' => (string) $role->name,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $roleNames
     */
    public static function assignRoles(int $userId, array $roleNames): void
    {
        $roleNames = array_values(array_filter(array_map('strval', $roleNames)));
        $sales = DB::connection('salescrm');

        if ($roleNames === []) {
            return;
        }

        $roleIds = $sales->table('roles')
            ->whereIn('name', $roleNames)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $sales->table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'model_type' => self::MODEL_TYPE,
                    'model_id' => $userId,
                ],
                [],
            );
        }
    }

    /**
     * @param  list<string>  $roleNames
     */
    public static function syncRoles(int $userId, array $roleNames): void
    {
        $sales = DB::connection('salescrm');

        $sales->table('model_has_roles')
            ->where('model_type', self::MODEL_TYPE)
            ->where('model_id', $userId)
            ->delete();

        self::assignRoles($userId, $roleNames);
    }

    public static function revokeAllRoles(int $userId): void
    {
        self::revokeAllRolesForUsers([$userId]);
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function revokeAllRolesForUsers(array $userIds): void
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));

        if ($userIds === []) {
            return;
        }

        DB::connection('salescrm')
            ->table('model_has_roles')
            ->where('model_type', self::MODEL_TYPE)
            ->whereIn('model_id', $userIds)
            ->delete();
    }
}
