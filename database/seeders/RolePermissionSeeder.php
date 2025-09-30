<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Station Management
            'view_stations',
            'create_stations',
            'edit_stations',
            'delete_stations',

            // Train Management
            'view_trains',
            'create_trains',
            'edit_trains',
            'delete_trains',

            // Stop Management
            'view_stops',
            'create_stops',
            'edit_stops',
            'delete_stops',

            // Train Trip Management
            'view_train_trips',
            'create_train_trips',
            'edit_train_trips',
            'delete_train_trips',

            // User Management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'ban_users',
            'unban_users',

            // Assignment Management
            'view_assignments',
            'edit_assignments',
            'delete_assignments',

            // Community Management
            'view_communities',
            'edit_communities',
            'delete_communities',
            'moderate_messages',
            'delete_messages',

            // Badge & Reward Management
            'view_badges',
            'create_badges',
            'edit_badges',
            'delete_badges',
            'view_rewards',
            'grant_rewards',
            'manage_user_badges',

            // Admin Management
            'view_admins',
            'create_admins',
            'edit_admins',
            'delete_admins',

            // System Management
            'view_dashboard',
            'view_analytics',
            'view_system_logs',
            'manage_settings',
            'backup_system',
            'maintenance_mode',

            // Real-time Management
            'monitor_live_trains',
            'update_train_locations',
            'send_announcements',

            // Content Management
            'manage_translations',
            'manage_static_content',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'admin'
            ]);
        }

        $this->command->info('Created ' . count($permissions) . ' permissions');
    }

    private function createRoles(): void
    {
        $roles = [
            'super_admin' => 'Super Administrator with full system access',
            'admin' => 'Administrator with most permissions',
            'train_manager' => 'Train operations and schedule management',
            'community_moderator' => 'Community content moderation',
            'operator' => 'Basic operations and monitoring',
            'viewer' => 'Read-only access to system data',
        ];

        foreach ($roles as $name => $description) {
            Role::create([
                'name' => $name,
                'guard_name' => 'admin',
            ]);
        }

        $this->command->info('Created ' . count($roles) . ' roles');
    }

    private function assignPermissionsToRoles(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::findByName('super_admin', 'admin');
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - Most permissions except critical system ones
        $admin = Role::findByName('admin', 'admin');
        $admin->givePermissionTo([
            // Station & Train Management
            'view_stations', 'create_stations', 'edit_stations',
            'view_trains', 'create_trains', 'edit_trains',
            'view_stops', 'create_stops', 'edit_stops',
            'view_train_trips', 'create_train_trips', 'edit_train_trips',

            // User Management
            'view_users', 'edit_users', 'ban_users', 'unban_users',

            // Assignment & Community
            'view_assignments', 'edit_assignments',
            'view_communities', 'edit_communities', 'moderate_messages', 'delete_messages',

            // Badges & Rewards
            'view_badges', 'create_badges', 'edit_badges',
            'view_rewards', 'grant_rewards', 'manage_user_badges',

            // System Access
            'view_dashboard', 'view_analytics',
            'monitor_live_trains', 'update_train_locations', 'send_announcements',
        ]);

        // Train Manager - Train operations focused
        $trainManager = Role::findByName('train_manager', 'admin');
        $trainManager->givePermissionTo([
            'view_stations', 'edit_stations',
            'view_trains', 'create_trains', 'edit_trains',
            'view_stops', 'create_stops', 'edit_stops',
            'view_train_trips', 'create_train_trips', 'edit_train_trips',
            'view_dashboard', 'monitor_live_trains', 'update_train_locations',
            'view_assignments',
        ]);

        // Community Moderator - Content moderation focused
        $moderator = Role::findByName('community_moderator', 'admin');
        $moderator->givePermissionTo([
            'view_users', 'ban_users', 'unban_users',
            'view_communities', 'moderate_messages', 'delete_messages',
            'view_badges', 'view_rewards',
            'view_dashboard',
        ]);

        // Operator - Basic monitoring and operations
        $operator = Role::findByName('operator', 'admin');
        $operator->givePermissionTo([
            'view_stations', 'view_trains', 'view_stops', 'view_train_trips',
            'view_users', 'view_assignments', 'view_communities',
            'view_dashboard', 'monitor_live_trains',
        ]);

        // Viewer - Read-only access
        $viewer = Role::findByName('viewer', 'admin');
        $viewer->givePermissionTo([
            'view_stations', 'view_trains', 'view_stops', 'view_train_trips',
            'view_users', 'view_assignments', 'view_communities',
            'view_badges', 'view_rewards', 'view_dashboard',
        ]);

        $this->command->info('Assigned permissions to all roles');
    }
}