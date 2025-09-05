<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('user_profiles', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->unique()->nullable();
            $table->string('public_email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('location')->nullable();
            $table->text('biography')->nullable();
            $table->json('skills')->nullable();
            $table->json('social_links')->nullable();
            $table->json('contact_channels')->nullable();

            // Settings
            $table->string('preferred_theme')->default('system');
            $table->string('preferred_language')->default('system');

            $table->boolean('auto_load_external_images')->default(false);
            $table->timestamp('external_images_temp_until')->nullable();

            $table->boolean('auto_load_external_videos')->default(false);
            $table->timestamp('external_videos_temp_until')->nullable();

            $table->boolean('auto_load_external_resources')->default(false);
            $table->timestamp('external_resources_temp_until')->nullable();

            // Counts
            $table->integer('reports_count')->default(0);

            // Update info
            $table->timestamps();
        });

        DB::statement('ALTER TABLE user_profiles AUTO_INCREMENT = 100;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_profiles');
    }
};
