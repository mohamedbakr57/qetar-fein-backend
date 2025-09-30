<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Admin;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for admin guard
        $permissions = [
            // Admin Management
            'view admins',
            'create admins',
            'edit admins',
            'delete admins',

            // Train Management
            'view trains',
            'create trains',
            'edit trains',
            'delete trains',

            // Station Management
            'view stations',
            'create stations',
            'edit stations',
            'delete stations',

            // Route Management
            'view routes',
            'create routes',
            'edit routes',
            'delete routes',

            // Schedule Management
            'view schedules',
            'create schedules',
            'edit schedules',
            'delete schedules',

            // Operations
            'view train-trips',
            'create train-trips',
            'edit train-trips',
            'delete train-trips',

            'view passenger-assignments',
            'edit passenger-assignments',
            'delete passenger-assignments',

            // User Management
            'view users',
            'edit users',
            'delete users',

            // Community Management
            'view communities',
            'moderate communities',
            'delete communities',

            // Gamification
            'view badges',
            'create badges',
            'edit badges',
            'delete badges',

            'view rewards',
            'create rewards',
            'edit rewards',
            'delete rewards',

            // System
            'view system-logs',
            'manage system-settings',
            'backup system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'admin'
            ]);
        }

        // Create roles and assign permissions
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'admin'
        ]);
        $superAdmin->givePermissionTo(Permission::where('guard_name', 'admin')->get());

        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin'
        ]);
        $admin->givePermissionTo([
            'view admins', 'edit admins',
            'view trains', 'create trains', 'edit trains', 'delete trains',
            'view stations', 'create stations', 'edit stations', 'delete stations',
            'view routes', 'create routes', 'edit routes', 'delete routes',
            'view schedules', 'create schedules', 'edit schedules', 'delete schedules',
            'view train-trips', 'create train-trips', 'edit train-trips',
            'view passenger-assignments', 'edit passenger-assignments',
            'view users', 'edit users',
            'view communities', 'moderate communities',
            'view badges', 'create badges', 'edit badges',
            'view rewards', 'create rewards', 'edit rewards',
        ]);

        $operator = Role::firstOrCreate([
            'name' => 'Operator',
            'guard_name' => 'admin'
        ]);
        $operator->givePermissionTo([
            'view trains', 'edit trains',
            'view stations', 'edit stations',
            'view routes', 'edit routes',
            'view schedules', 'edit schedules',
            'view train-trips', 'create train-trips', 'edit train-trips',
            'view passenger-assignments', 'edit passenger-assignments',
            'view users',
            'view communities', 'moderate communities',
            'view badges',
            'view rewards',
        ]);

        $viewer = Role::firstOrCreate([
            'name' => 'Viewer',
            'guard_name' => 'admin'
        ]);
        $viewer->givePermissionTo([
            'view trains', 'view stations', 'view routes', 'view schedules',
            'view train-trips', 'view passenger-assignments', 'view users',
            'view communities', 'view badges', 'view rewards',
        ]);

        // Assign roles to existing admins
        $superAdminUser = Admin::where('email', 'admin@qatarfein.com')->first();
        if ($superAdminUser) {
            $superAdminUser->assignRole('Super Admin');
        }

        $managerUser = Admin::where('email', 'manager@qatarfein.com')->first();
        if ($managerUser) {
            $managerUser->assignRole('Admin');
        }

        $operatorUser = Admin::where('email', 'operator@qatarfein.com')->first();
        if ($operatorUser) {
            $operatorUser->assignRole('Operator');
        }
    }
}