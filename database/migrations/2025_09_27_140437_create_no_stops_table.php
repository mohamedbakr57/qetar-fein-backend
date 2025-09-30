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
        Schema::create('no_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('train_id')->constrained('trains')->onDelete('cascade');
            $table->integer('stop_number'); // Reference to skipped stop sequence
            $table->json('reason')->nullable(); // {"ar": "محطة غير تشغيلية", "en": "Station not operational"}
            $table->timestamps();

            // Index for performance
            $table->index(['train_id', 'stop_number'], 'idx_train_no_stop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('no_stops');
    }
};
