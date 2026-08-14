<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SpkPermissions;
use Database\Seeders\SpkPermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => null,
            'spk_role' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Admin SPK: buat/edit draft, tanpa submit/approve (untuk tes negatif submit).
     */
    public function adminSpk(): static
    {
        return $this->afterCreating(function (User $user): void {
            $this->assignSpkRole($user, 'ADMIN SPK', [
                SpkPermissions::VIEW,
                SpkPermissions::CREATE,
                SpkPermissions::EDIT_DRAFT,
            ]);
        });
    }

    /**
     * SPV Production: kirim draft ke Manager.
     */
    public function spvPrd(): static
    {
        return $this->afterCreating(function (User $user): void {
            $this->assignSpkRole($user, SpkPermissionSeeder::ROLE_SPV_PRODUCTION, [
                SpkPermissions::VIEW,
                SpkPermissions::CREATE,
                SpkPermissions::EDIT_DRAFT,
                SpkPermissions::SUBMIT,
            ]);
        });
    }

    /**
     * Manager Produksi: approve / reject.
     */
    public function managerProduksi(): static
    {
        return $this->afterCreating(function (User $user): void {
            $this->assignSpkRole($user, 'MANAGER', [
                SpkPermissions::VIEW,
                SpkPermissions::APPROVE,
                SpkPermissions::REJECT,
            ]);
        });
    }

    /**
     * Administrator: semua permission SPK.
     */
    public function administratorSpk(): static
    {
        return $this->afterCreating(function (User $user): void {
            $this->assignSpkRole($user, 'ADMINISTRATOR', SpkPermissions::all());
        });
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function assignSpkRole(User $user, string $roleName, array $permissionNames): void
    {
        app(SpkPermissionSeeder::class)->ensurePermissions();

        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            [
                'is_deleted' => 0,
                'created_by' => 'factory',
                'modified_by' => 'factory',
            ],
        );

        $ids = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->syncWithoutDetaching($ids);

        $user->forceFill(['role_id' => $role->id])->save();
        $user->unsetRelation('role');
        $user->load('role.permissions');
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
