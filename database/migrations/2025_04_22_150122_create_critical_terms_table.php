<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('critical_terms', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->string('name')->unique(); // Use name not term for consistency
            $table->string('language')->default('en'); // Assuming 'en' is the default language
            $table->integer('severity')->default(1); // Assuming severity is an integer between 1 and 5
            $table->string('created_by_role')->default('system'); // Assuming 'system' is the default role for the system user
            $table->unsignedBigInteger('created_by_user_id')->default(2); // Assuming 2 is the ID of the system user
            $table->foreign('created_by_user_id')->references('id')->on('users'); // Foreign key reference to users table

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('critical_terms');
    }
};
