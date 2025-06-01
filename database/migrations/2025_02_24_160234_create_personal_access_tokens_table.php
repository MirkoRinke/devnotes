<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            // Default
            $table->id();

            // Basic
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('device_fingerprint')->default('ba84207fca7c910c70f1a13943c1e054fc3c864fa69cfbe1cb045d3130bd92d7'); // default fingerprint for the device
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();

            // Update info
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('personal_access_tokens');
    }
};
