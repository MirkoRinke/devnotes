<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('user_reports', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('reportable');  // Polymorphic relationship (for Post|User|Comment)
            $table->unique(['user_id', 'reportable_id', 'reportable_type']);
            $table->string('type')->nullable(); // Simple type (post, user, comment)
            $table->text('reason')->nullable();
            $table->json('reportable_snapshot')->nullable();
            $table->integer('impact_value')->default(0);

            // Update info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_reports');
    }
};
