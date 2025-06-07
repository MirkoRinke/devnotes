<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Post;
use App\Models\UserFavorite;

class FavoriteSeeder extends Seeder {

    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->command->info('Seeding favorites...');

        $userIds = User::pluck('id')->toArray();

        foreach ($userIds as $userId) {
            /**
             * Get all posts that aren't owned by this user
             */
            $eligiblePosts = Post::where('user_id', '!=', $userId)->pluck('id')->toArray();

            if (count($eligiblePosts) > 0) {

                /**
                 * Randomly select a number of favorites for this user
                 * This number is between 0 and 5, but will never exceed the number of eligible posts.
                 */
                $maxPossibleFavorites = min(5, count($eligiblePosts));
                $favoritesCount = rand(0, $maxPossibleFavorites);

                if ($favoritesCount > 0) {

                    // Shuffle eligible post IDs to randomize selection
                    shuffle($eligiblePosts);

                    // Select a random subset of posts to favorite
                    $selectedPosts = array_slice($eligiblePosts, 0, $favoritesCount);

                    // Create favorites for each selected post
                    foreach ($selectedPosts as $postId) {
                        $favorite = UserFavorite::firstOrCreate([
                            'user_id' => $userId,
                            'post_id' => $postId,
                        ]);

                        // Only increment the favorite count if a new favorite was created
                        if ($favorite->wasRecentlyCreated) {
                            Post::where('id', $postId)->increment('favorite_count');
                        }
                    }
                }
            }
        }
        $this->command->info('Favorites seeded successfully!');
    }
}
