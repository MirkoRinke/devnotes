<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('comments', function (Blueprint $table) {
            // Default
            $table->id();
            $table->timestamps();

            // Basic
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->text('parent_content')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('depth')->default(0);

            // Counts
            $table->integer('likes_count')->default(0);
            $table->integer('reports_count')->default(0);

            // Update info
            $table->boolean('is_updated')->default(false);
            $table->string('updated_by_role')->nullable();

            // Moderation info
            $table->json('moderation_info')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('comments');
    }
};
