<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('community_messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('verification_type', ['confirm', 'dispute']);
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index('verification_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_verifications');
    }
};
