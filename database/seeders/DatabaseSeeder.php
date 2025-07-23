<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    /**
     * Seed the application's database.
     */
    public function run(): void {
        $this->call([
            ApiKeySeeder::class,
            UserSeeder::class,
            PostAllowedValueSeeder::class,
            UserProfileFavoriteLanguageSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            LikeSeeder::class,
            FavoriteSeeder::class,
            ForbiddenNameSeeder::class,
            CriticalTermSeeder::class,
        ]);
        $this->command->info('Database seeding completed successfully!');
    }
}
