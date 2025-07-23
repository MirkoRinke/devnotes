<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('forbidden_names', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->string('name')->unique();
            $table->string('match_type')->default('exact');
            $table->string('created_by_role')->default('system');
            $table->unsignedBigInteger('created_by_user_id')->default(2);
            $table->foreign('created_by_user_id')->references('id')->on('users');

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('forbidden_names');
    }
};
