<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('train_id')->constrained('trains')->onDelete('cascade');
            $table->foreignId('station_id')->constrained('stations');
            $table->integer('stop_number'); // Sequence order in journey (1, 2, 3...)
            $table->time('arrival_time')->nullable();
            $table->time('departure_time');
            $table->string('platform', 10)->nullable();
            $table->integer('stop_duration_minutes')->default(0);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->boolean('is_major_stop')->default(false);
            $table->json('notes')->nullable();
            $table->timestamps();

            // Unique constraint: each train can only have one stop with the same stop_number
            $table->unique(['train_id', 'stop_number'], 'unique_train_stop');

            // Indexes for performance
            $table->index(['train_id', 'stop_number'], 'idx_train_sequence');
            $table->index('station_id', 'idx_station_stops');
            $table->index(['station_id', 'departure_time'], 'idx_station_departures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};
