<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create permissions
        $permissions = [
            'manage-teams',
            'manage-users',
            'manage-clients',
            'manage-sops',
            'execute-sops',
            'view-audit-logs',
            'access-client-portal',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Create roles and assign permissions
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->syncPermissions(Permission::all());

        $roleEditor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $roleEditor->syncPermissions([
            'manage-clients',
            'manage-sops',
            'execute-sops',
        ]);

        $roleEjecutor = Role::firstOrCreate(['name' => 'ejecutor', 'guard_name' => 'web']);
        $roleEjecutor->syncPermissions([
            'execute-sops',
        ]);

        $roleCliente = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $roleCliente->syncPermissions([
            'access-client-portal',
        ]);

        // 3. Create initial Admin User if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@sopforge.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin2026**'),
                'email_verified_at' => now(),
            ]
        );

        // 4. Create initial Team and assign to Admin
        $team = Team::firstOrCreate(
            ['name' => 'Agencia Principal'],
            [
                'user_id' => $admin->id,
                'personal_team' => false,
            ]
        );

        if (! $admin->current_team_id) {
            $admin->current_team_id = $team->id;
            $admin->save();
        }

        // Attach to team_user if not already attached and not owner
        if (! $team->users()->where('user_id', $admin->id)->exists() && $team->user_id !== $admin->id) {
            $team->users()->attach($admin, ['role' => 'admin']);
        }

        if (! $admin->hasRole('admin')) {
            $admin->assignRole($roleAdmin);
        }
    }
}