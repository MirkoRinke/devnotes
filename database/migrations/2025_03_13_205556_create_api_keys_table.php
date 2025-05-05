<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('api_keys', function (Blueprint $table) {
            // Default
            $table->id();                                  // Auto incrementing ID

            // Basic
            $table->string('name');                        // Name of the API key
            $table->string('key')->unique();               // The API key itself
            $table->boolean('active')->default(true);      // Whether the API key is active

            // Update info
            $table->timestamps();                          // Created at and updated at timestamps
            $table->timestamp('last_used_at')->nullable(); // Last time the API key was used
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('api_keys');
    }
};
