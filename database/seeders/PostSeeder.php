<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder {

    /**
     * Run the database seeds.
     */
    public function run(): void {

        // Create 500 posts using the PostFactory
        Post::factory(500)->create();
    }
}
