<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\SpkPermissions;
use Illuminate\Database\Seeder;

class SpkPermissionSeeder extends Seeder
{
    public const ROLE_SPV_PRODUCTION = 'SPV PRODUCTION';

    /**
     * Seed permission spk.* dan assign ke role existing (idempotent, non-destructive).
     */
    public function run(): void
    {
        $this->ensurePermissions();

        $administrator = Role::query()->where('name', 'ADMINISTRATOR')->first();
        if ($administrator !== null) {
            $this->attachPermissions($administrator, SpkPermissions::all());
        }

        $manager = Role::query()->where('name', 'MANAGER')->first();
        if ($manager !== null) {
            $this->attachPermissions($manager, [
                SpkPermissions::VIEW,
                SpkPermissions::APPROVE,
                SpkPermissions::REJECT,
            ]);
        }

        $spv = Role::query()->firstOrCreate(
            ['name' => self::ROLE_SPV_PRODUCTION],
            [
                'is_deleted' => 0,
                'created_by' => 'system',
                'modified_by' => 'system',
            ],
        );

        $this->attachPermissions($spv, [
            SpkPermissions::VIEW,
            SpkPermissions::CREATE,
            SpkPermissions::EDIT_DRAFT,
            SpkPermissions::SUBMIT,
        ]);
    }

    /**
     * @return array<string, Permission>
     */
    public function ensurePermissions(): array
    {
        $map = [];

        foreach (SpkPermissions::definitions() as $name => $meta) {
            $map[$name] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $meta['display_name'],
                    'description' => $meta['description'],
                    'module' => 'spk',
                    'category' => $meta['category'],
                    'is_active' => 1,
                ],
            );
        }

        return $map;
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function attachPermissions(Role $role, array $permissionNames): void
    {
        $ids = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->syncWithoutDetaching($ids);
    }
}
