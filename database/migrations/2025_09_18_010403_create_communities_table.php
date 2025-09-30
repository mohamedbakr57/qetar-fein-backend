<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('train_trips')->onDelete('cascade');
            $table->json('name')->nullable(); // Auto-generated based on route
            $table->json('description')->nullable();
            $table->integer('member_count')->default(0);
            $table->integer('message_count')->default(0);
            $table->enum('status', ['active', 'archived', 'closed'])->default('active');
            $table->timestamp('auto_archive_at')->nullable(); // 24h after trip completion
            $table->timestamps();

            $table->unique('trip_id', 'unique_trip_community');
            $table->index('status');
            $table->index('auto_archive_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('communities');
    }
};