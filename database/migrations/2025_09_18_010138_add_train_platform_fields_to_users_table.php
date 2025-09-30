<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add phone as the primary identifier
            $table->string('phone', 20)->unique()->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');

            // Make email nullable since phone is primary
            $table->string('email')->nullable()->change();

            // Additional user fields
            $table->string('avatar')->nullable()->after('name');
            $table->date('date_of_birth')->nullable()->after('avatar');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->enum('preferred_language', ['ar', 'en'])->default('ar')->after('gender');
            $table->json('notification_preferences')->nullable()->after('preferred_language');

            // Qatar Fein specific fields
            $table->timestamp('ad_free_until')->nullable()->after('notification_preferences');
            $table->integer('total_assignments')->default(0)->after('ad_free_until');
            $table->integer('successful_assignments')->default(0)->after('total_assignments');
            $table->integer('reward_points')->default(0)->after('successful_assignments');
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active')->after('reward_points');
            $table->timestamp('last_active_at')->nullable()->after('status');

            // Add indexes
            $table->index('phone');
            $table->index('status');
            $table->index('last_active_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'phone_verified_at', 'avatar', 'date_of_birth', 'gender',
                'preferred_language', 'notification_preferences', 'ad_free_until',
                'total_assignments', 'successful_assignments', 'reward_points',
                'status', 'last_active_at'
            ]);

            $table->string('email')->nullable(false)->change();
        });
    }
};