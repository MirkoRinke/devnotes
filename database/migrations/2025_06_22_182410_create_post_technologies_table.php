<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('post_technologies', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_allowed_value_id')->constrained()->onDelete('cascade');
            $table->unique(['post_id', 'post_allowed_value_id']);

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('post_technologies');
    }
};
