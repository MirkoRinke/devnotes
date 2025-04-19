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
            $table->foreignId('user_id')->nullable()->constrained('users', 'id');
            $table->string('title');
            $table->text('code')->nullable();
            $table->text('description');
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->json('resources')->nullable();
            $table->json('external_source_previews')->nullable();
            $table->json('language'); // programming language
            $table->string('category');
            $table->string('post_type')->default('snippet'); // snippet, tutorial, feedback, showcase, question, etc.
            $table->json('technology')->nullable();; // technology used (e.g., Angular, React, etc.)
            $table->json('tags');
            $table->string('status')->default('draft'); // draft, published, archived            

            // Counts
            $table->integer('favorite_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('reports_count')->default(0);
            $table->integer('comments_count')->default(0); // number of comments

            // Update info
            $table->boolean('is_updated')->default(false);
            $table->string('updated_by_role')->nullable();
            $table->timestamp('last_comment_at')->nullable(); // last comment add, update, delete

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
