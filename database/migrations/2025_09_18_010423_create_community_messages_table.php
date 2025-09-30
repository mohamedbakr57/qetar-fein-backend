<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('community_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // NULL for guest users
            $table->string('guest_identifier')->nullable(); // For guest users
            $table->foreignId('station_id')->constrained('stations');
            $table->integer('time_passed_minutes'); // Minutes since departure
            $table->enum('message_type', ['status_update', 'delay_report', 'facility_report', 'general'])->default('status_update');
            $table->json('additional_data')->nullable(); // For structured data like delay reasons
            $table->boolean('is_verified')->default(false); // Verified by other passengers
            $table->integer('verification_count')->default(0);
            $table->timestamps();

            $table->index(['community_id', 'created_at'], 'idx_community_time');
            $table->index('user_id', 'idx_user_messages');
            $table->index(['station_id', 'time_passed_minutes'], 'idx_station_messages');
        });
    }

    public function down()
    {
        Schema::dropIfExists('community_messages');
    }
};