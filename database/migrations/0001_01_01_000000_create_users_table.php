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
        Schema::create('users', function (Blueprint $table) {
            // Default
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('github_id')->unique()->nullable();
            $table->string('password');
            $table->rememberToken();

            // Basic
            $table->string('display_name')->unique();
            $table->string('role')->default('user');

            // Avatar-System
            $table->json('avatar_items');

            // Ban info
            $table->timestamp('is_banned')->nullable();
            $table->boolean('was_ever_banned')->default(false);

            // Moderation info
            $table->json('moderation_info');

            // Account info
            $table->timestamp('privacy_policy_accepted_at')->nullable();
            $table->timestamp('terms_of_service_accepted_at')->nullable();
            $table->enum('account_purpose', ['regular', 'guest'])->default('regular')->nullable(false);

            // Update info
            $table->timestamps();
            $table->timestamp('last_post_created_at')->nullable();
            $table->timestamp('last_post_updated_at')->nullable();
        });

        DB::statement('ALTER TABLE users AUTO_INCREMENT = 100;');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
