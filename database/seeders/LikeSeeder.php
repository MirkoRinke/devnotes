<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\UserLike;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;

class LikeSeeder extends Seeder {
    /**
     * Run the database seeds.
     * Creates likes for both posts and comments.
     */
    public function run(): void {
        $this->createPostLikes();
        $this->createCommentLikes();
        $this->updatePostLikesCounts();
        $this->updateCommentLikesCounts();
    }

    /**
     * Creates likes for posts from different users
     * 
     * @example | $this->createPostLikes();
     */
    private function createPostLikes(): void {
        $this->command->info('Seeding likes for posts...');

        // Get all posts with their author IDs
        $posts = Post::select('id', 'user_id')->get();

        // Get all user IDs
        $allUserIds = User::whereNotIn('role', ['system'])->pluck('id')->toArray();

        foreach ($posts as $post) {
            // Get IDs of users who aren't the post author
            $likerIds = array_diff($allUserIds, [$post->user_id]);

            /**
             * Randomly select a number of users to like this post
             * This number is between 0 and 5, but will never exceed the number of eligible likers.
             */
            $numLikers = rand(0, min(5, count($likerIds)));

            if ($numLikers > 0) {

                // Shuffle potential liker IDs to randomize selection
                shuffle($likerIds);

                $selectedLikers = array_slice($likerIds, 0, $numLikers);

                // Create likes for each selected liker
                foreach ($selectedLikers as $likerId) {
                    UserLike::firstOrCreate(
                        [
                            'user_id' => $likerId,
                            'likeable_id' => $post->id,
                            'likeable_type' => Post::class
                        ],
                        [
                            'type' => 'post',
                        ]
                    );
                }
            }
        }
        $this->command->info('Likes for posts have been seeded successfully.');
    }

    /**
     * Creates likes for comments from different users
     * 
     * @example | $this->createCommentLikes();
     */
    private function createCommentLikes(): void {
        $this->command->info('Seeding likes for comments...');

        // Get all comments with their author IDs
        $comments = Comment::select('id', 'user_id')->get();

        // Get all user IDs
        $allUserIds = User::pluck('id')->toArray();

        foreach ($comments as $comment) {
            // Get IDs of users who aren't the comment author
            $likerIds = array_diff($allUserIds, [$comment->user_id]);

            /**
             * Randomly select a number of users to like this comment
             * This number is between 0 and 5, but will never exceed the number of eligible likers.
             */
            $numLikers = rand(0, min(5, count($likerIds)));

            if ($numLikers > 0) {

                // Shuffle potential liker IDs to randomize selection
                shuffle($likerIds);

                $selectedLikers = array_slice($likerIds, 0, $numLikers);

                foreach ($selectedLikers as $likerId) {
                    UserLike::firstOrCreate(
                        [
                            'user_id' => $likerId,
                            'likeable_id' => $comment->id,
                            'likeable_type' => Comment::class
                        ],
                        [
                            'type' => 'comment',
                        ]
                    );
                }
            }
        }
        $this->command->info('Likes for comments have been seeded successfully.');
    }

    /**
     * Updates the likes_count specifically for posts
     * 
     * @example | $this->updatePostLikesCounts();
     */
    private function updatePostLikesCounts(): void {
        $this->command->info('Updating likes count for posts...');

        // Get all likes for posts
        $postLikesCollection = UserLike::where('likeable_type', Post::class)->select('likeable_id')->get();

        // Group likes by post ID
        $groupedPostLikes = $postLikesCollection->groupBy('likeable_id');

        // Count likes for each post
        $postLikeCounts = [];
        foreach ($groupedPostLikes as $postId => $likesGroup) {
            $postLikeCounts[$postId] = $likesGroup->count();
        }

        // Update the likes_count on each post
        foreach ($postLikeCounts as $postId => $count) {
            Post::where('id', $postId)->update(['likes_count' => $count]);
        }
    }

    /**
     * Updates the likes_count specifically for comments
     * 
     * @example | $this->updateCommentLikesCounts();
     */
    private function updateCommentLikesCounts(): void {
        $this->command->info('Updating likes count for comments...');

        // Get all likes for comments
        $commentLikesCollection = UserLike::where('likeable_type', Comment::class)->select('likeable_id')->get();

        // Group likes by comment ID
        $groupedCommentLikes = $commentLikesCollection->groupBy('likeable_id');

        // Count likes for each comment
        $commentLikeCounts = [];
        foreach ($groupedCommentLikes as $commentId => $likesGroup) {
            $commentLikeCounts[$commentId] = $likesGroup->count();
        }

        // Update the likes_count on each comment
        foreach ($commentLikeCounts as $commentId => $count) {
            Comment::where('id', $commentId)->update(['likes_count' => $count]);
        }
    }
}
