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

            // Basic
            $table->foreignId('user_id')->nullable()->constrained('users', 'id');
            $table->string('title');
            $table->mediumText('code')->nullable(); // We using mediumText for Puffer to handle Emojis etc. max: 65535 characters
            $table->mediumText('description');  // We using mediumText for Puffer to handle Emojis etc. max: 65535 characters
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->json('resources')->nullable();
            $table->json('external_source_previews')->nullable();
            $table->string('category'); // category of the post (e.g., Frontend, Backend, etc.)
            $table->string('post_type'); // snippet, tutorial, feedback, showcase, question, etc.
            $table->string('status'); // status of the post (e.g., draft, private, published, archived)
            $table->string('syntax_highlighting')->nullable(); // programming language for syntax highlighting (e.g., php, javascript, python, etc.)

            // Counts
            $table->integer('favorite_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('reports_count')->default(0);
            $table->integer('comments_count')->default(0);

            // Update info
            $table->boolean('is_updated')->default(false);
            $table->string('updated_by_role')->nullable();
            $table->timestamp('comments_updated_at')->nullable(); // last comment add, update, delete

            // History
            $table->json('history')->nullable(); // history of changes made to the post

            // Moderation info
            $table->json('moderation_info')->nullable();

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('posts');
    }
};
