<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $superAdmin->assignRole('super_admin');

        // Create Train Manager
        $trainManager = Admin::create([
            'name' => 'Train Manager',
            'email' => 'manager@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'train_manager',
            'status' => 'active',
        ]);
        $trainManager->assignRole('train_manager');

        // Create Admin
        $admin = Admin::create([
            'name' => 'System Admin',
            'email' => 'sysadmin@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        // Create Community Moderator
        $moderator = Admin::create([
            'name' => 'Community Moderator',
            'email' => 'moderator@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'community_moderator',
            'status' => 'active',
        ]);
        $moderator->assignRole('community_moderator');

        // Create System Operator
        $operator = Admin::create([
            'name' => 'System Operator',
            'email' => 'operator@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'status' => 'active',
        ]);
        $operator->assignRole('operator');

        // Create Viewer
        $viewer = Admin::create([
            'name' => 'Data Viewer',
            'email' => 'viewer@qatarfein.com',
            'password' => Hash::make('password123'),
            'role' => 'viewer',
            'status' => 'active',
        ]);
        $viewer->assignRole('viewer');

        $this->command->info('Created admin users with proper roles and permissions');
    }
}