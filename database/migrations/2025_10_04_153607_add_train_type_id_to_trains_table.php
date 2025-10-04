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
        Schema::table('trains', function (Blueprint $table) {
            // Add train_type_id column after id
            $table->foreignId('train_type_id')->nullable()->after('id')->constrained('train_types')->onDelete('set null');

            // Make the old 'type' column nullable for now (we'll migrate data then remove it)
            $table->string('type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trains', function (Blueprint $table) {
            $table->dropForeign(['train_type_id']);
            $table->dropColumn('train_type_id');
            $table->string('type')->nullable(false)->change();
        });
    }
};
