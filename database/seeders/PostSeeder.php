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
        $this->command->info('Seeding posts...');

        $multiplier = (int) 1;

        $postsCount = (int) 500;

        /**
         * Create posts in the database.
         */
        for ($i = 0; $i < $multiplier; $i++) {
            Post::factory($postsCount)->create();

            $this->command->info("Created " . ($postsCount * $multiplier) . " posts. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }
    }
}
