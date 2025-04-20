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
            $table->id();
            $table->string('name')->unique();
            $table->enum('match_type', ['exact', 'partial'])->default('exact');
            $table->string('created_by_role')->default('system'); // Assuming 'system' is the default role for the system user
            $table->unsignedBigInteger('created_by_user_id')->default(2); // Assuming 2 is the ID of the system user

            $table->foreign('created_by_user_id')->references('id')->on('users');
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
