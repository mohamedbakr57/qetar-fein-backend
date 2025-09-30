<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trains', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->json('name'); // {"ar": "قطار الحرمين السريع", "en": "Haramain High Speed Rail"}
            $table->json('description')->nullable();
            $table->enum('type', ['passenger', 'freight', 'high_speed', 'metro'])->default('passenger');
            $table->json('operator'); // {"ar": "الخطوط الحديدية السعودية", "en": "Saudi Railways Organization"}
            $table->integer('capacity');
            $table->integer('max_speed')->nullable();
            $table->json('amenities')->nullable(); // ["wifi", "dining", "ac", "prayer_area"]
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('trains');
    }
};