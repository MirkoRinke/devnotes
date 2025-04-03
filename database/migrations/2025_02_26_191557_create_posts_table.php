<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            // Default
            $table->id();
            $table->timestamps();

            // Basic
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string('title');
            $table->text('code')->nullable();
            $table->text('description');
            $table->json('resources')->nullable();
            $table->json('images')->nullable();
            $table->json('external_source_previews')->nullable();
            $table->string('language')->nullable();
            $table->string('category')->nullable();
            $table->json('tags');
            $table->string('status')->default('draft'); // draft, published, archived

            // Counts
            $table->integer('favorite_count')->default(0);
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
        Schema::dropIfExists('posts');
    }
};
