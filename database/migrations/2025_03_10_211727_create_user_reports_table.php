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
            $table->timestamps();

            // Basic
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who created the report
            $table->morphs('reportable');  // Polymorphic relationship (for Post|User|Comment)
            $table->unique(['user_id', 'reportable_id', 'reportable_type']); // A user can only report an entity once
            $table->string('type')->nullable(); // Simple type (post, user, comment)
            $table->text('reason')->nullable(); // Reason for the report
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_reports');
    }
};
