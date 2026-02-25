<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder {

    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->command->info('Seeding posts...');

        $multiplier = (int) 20;

        $postsCount = (int) 50;

        $collectedUserIds = [];

        /**
         * Create posts in the database.
         */
        for ($i = 0; $i < $multiplier; $i++) {
            $posts = Post::factory($postsCount)->create();

            foreach ($posts as $post) {
                $collectedUserIds[] = $post->user_id;
            }

            $this->command->info("Created " . ($postsCount * $multiplier) . " posts. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }

        $uniqueUserIds = array_unique($collectedUserIds);

        User::whereIn('id', $uniqueUserIds)->update(['last_post_created_at' => now()]);
    }
}
