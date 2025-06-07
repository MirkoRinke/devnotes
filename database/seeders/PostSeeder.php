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

        $postsCount = 500;
        $progressInterval = 10;


        /**
         * Create posts in the database.
         */
        for ($i = 0; $i < $postsCount; $i++) {
            Post::factory(1)->create();

            if ($i % $progressInterval == 0) {
                $this->command->info('Posts created successfully! ' . ($progressInterval + $i));
            }
        }
    }
}
