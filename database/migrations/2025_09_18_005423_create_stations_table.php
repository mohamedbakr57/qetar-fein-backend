<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->json('name'); // {"ar": "محطة الرياض", "en": "Riyadh Station"}
            $table->json('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('elevation')->nullable();
            $table->json('city'); // {"ar": "الرياض", "en": "Riyadh"}
            $table->json('region')->nullable();
            $table->string('country_code', 2)->default('SA');
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->json('facilities')->nullable(); // ["wifi", "restaurant", "parking"]
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('status');
            $table->index('order_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stations');
    }
};