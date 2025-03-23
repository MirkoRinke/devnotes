<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who created the like
            $table->morphs('likeable');  // Polymorphic relationship (for Post|Comment etc.)
            $table->string('type')->nullable(); // Simple type (post, comment)
            $table->unique(['user_id', 'likeable_id', 'likeable_type']); // A user can only like an entity once
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('likes');
    }
};
