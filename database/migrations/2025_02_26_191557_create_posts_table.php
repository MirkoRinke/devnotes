<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void  {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id');
            $table->string('title');
            $table->text('code');
            $table->text('description');
            $table->json('resources')->nullable();
            $table->string('language');
            $table->string('category')->nullable();
            $table->json('tags');
            $table->timestamps();
            $table->string('status')->default('draft'); // draft, published, archived
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('posts');
    }
};



