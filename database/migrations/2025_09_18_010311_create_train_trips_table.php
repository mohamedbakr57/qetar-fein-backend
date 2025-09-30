<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('train_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('train_id')->constrained('trains');
            $table->date('trip_date');
            $table->timestamp('actual_departure_time')->nullable();
            $table->timestamp('actual_arrival_time')->nullable();
            $table->timestamp('estimated_departure_time');
            $table->timestamp('estimated_arrival_time');
            $table->integer('delay_minutes')->default(0);
            $table->foreignId('current_station_id')->nullable()->constrained('stations');
            $table->foreignId('next_station_id')->nullable()->constrained('stations');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->decimal('speed_kmh', 5, 2)->default(0);
            $table->enum('status', ['scheduled', 'boarding', 'departed', 'in_transit', 'arrived', 'cancelled', 'delayed'])->default('scheduled');
            $table->integer('passenger_count')->default(0);
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();

            $table->unique(['train_id', 'trip_date'], 'unique_train_date');
            $table->index('trip_date');
            $table->index('status');
            $table->index(['current_latitude', 'current_longitude'], 'idx_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('train_trips');
    }
};