<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('passenger_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('train_trips')->onDelete('cascade');
            $table->foreignId('boarding_station_id')->constrained('stations');
            $table->foreignId('destination_station_id')->constrained('stations');
            $table->timestamp('assignment_time')->useCurrent();
            $table->timestamp('boarding_time')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->boolean('location_sharing_enabled')->default(false);
            $table->timestamp('last_location_update')->nullable();
            $table->enum('status', ['assigned', 'boarded', 'in_transit', 'arrived', 'cancelled'])->default('assigned');
            $table->boolean('completion_verified')->default(false);
            $table->integer('reward_points_earned')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'trip_id'], 'unique_user_trip');
            $table->index(['user_id', 'status'], 'idx_user_assignments');
            $table->index(['trip_id', 'status'], 'idx_trip_assignments');
        });
    }

    public function down()
    {
        Schema::dropIfExists('passenger_assignments');
    }
};