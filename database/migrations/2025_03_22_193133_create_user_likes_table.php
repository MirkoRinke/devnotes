<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('user_likes', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('likeable');  // Polymorphic relationship (for Post|Comment etc.)
            $table->unique(['user_id', 'likeable_id', 'likeable_type']);
            $table->string('type')->nullable(); // Simple type (post, comment)

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_likes');
    }
};
