<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles and permissions first
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@qatarfein.com',
        ]);

        // Seed admin users with roles
        $this->call([
            AdminSeeder::class,
        ]);

        // Seed train scheduling system only (preserve assignments & communities)
        $this->call([
            // Import Egyptian train data according to business rules
            TrainTypeSeeder::class,
            BusinessRulesTrainSeeder::class,
        ]);
    }
}
