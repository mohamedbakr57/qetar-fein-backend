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
        Schema::create('train_types', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {en: 'Express', ar: 'إكسبريس'}
            $table->string('code', 10)->unique(); // HSR, AC, VIP, etc.
            $table->json('description'); // {en: 'description', ar: 'وصف'}
            $table->json('features'); // ['wifi', 'ac', 'food_service']
            $table->enum('comfort_level', ['standard', 'comfort', 'premium', 'luxury'])->default('standard');
            $table->decimal('price_multiplier', 3, 2)->default(1.00); // 1.00 to 5.00
            $table->integer('max_speed')->default(80); // km/h
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('train_types');
    }
};
