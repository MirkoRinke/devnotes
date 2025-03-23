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
        // Get all user IDs from the database
        $userIds = User::pluck('id')->toArray();

        // Process each user to create their favorites
        foreach ($userIds as $userId) {
            // First get all posts that aren't owned by this user
            $eligiblePosts = Post::where('user_id', '!=', $userId)->pluck('id')->toArray();

            // Only proceed if there are eligible posts for this user
            if (count($eligiblePosts) > 0) {
                // Randomly determine how many favorites this user will have (0-2)
                // But limit to the number of available posts
                $maxPossibleFavorites = min(2, count($eligiblePosts));
                $favoritesCount = rand(0, $maxPossibleFavorites);

                // Skip users who won't have any favorites
                if ($favoritesCount > 0) {
                    // Shuffle eligible post IDs to randomize selection
                    shuffle($eligiblePosts);

                    // Select a random subset of posts to favorite
                    $selectedPosts = array_slice($eligiblePosts, 0, $favoritesCount);

                    // Create favorites for each selected post
                    foreach ($selectedPosts as $postId) {
                        // Use firstOrCreate to check and create in one step
                        $favorite = UserFavorite::firstOrCreate(
                            [
                                'user_id' => $userId,
                                'post_id' => $postId,
                            ],
                            [
                                'created_at' => now(),
                                'updated_at' => now()
                            ]
                        );

                        // Only increment the favorite count if a new favorite was created
                        if ($favorite->wasRecentlyCreated) {
                            Post::where('id', $postId)->increment('favorite_count');
                        }
                    }
                }
            }
        }
    }
}
