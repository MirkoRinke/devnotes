<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('user_profiles', function (Blueprint $table) {
            // Default
            $table->id();
            $table->timestamps();

            // Basic
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->unique()->nullable();
            $table->string('public_email')->nullable();
            $table->string('website')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('location')->nullable();
            $table->text('biography')->nullable();
            $table->json('skills')->nullable();
            $table->json('social_links')->nullable();
            $table->json('contact_channels')->nullable();

            // Settings
            $table->boolean('auto_load_external_images')->default(false);
            $table->timestamp('external_images_temp_until')->nullable();

            $table->boolean('auto_load_external_videos')->default(false);
            $table->timestamp('external_videos_temp_until')->nullable();

            $table->boolean('auto_load_external_resources')->default(false);
            $table->timestamp('external_resources_temp_until')->nullable();

            // Counts
            $table->integer('reports_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_profiles');
    }
};
