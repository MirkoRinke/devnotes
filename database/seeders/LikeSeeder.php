<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Like;
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
     */
    private function createPostLikes(): void {
        // Get all posts with their author IDs
        $posts = Post::select('id', 'user_id')->get();

        // Get all user IDs dynamically
        $allUserIds = User::pluck('id')->toArray();

        foreach ($posts as $post) {
            // Get IDs of users who aren't the post author
            $likerIds = array_diff($allUserIds, [$post->user_id]);

            // Randomly determine how many users will like this post (0-2)
            $numLikers = rand(0, min(2, count($likerIds)));

            // If we'll have likers, select them randomly
            if ($numLikers > 0) {
                // Shuffle potential liker IDs to randomize selection
                shuffle($likerIds);
                $selectedLikers = array_slice($likerIds, 0, $numLikers);

                foreach ($selectedLikers as $likerId) {
                    // Use firstOrCreate to prevent duplicates efficiently
                    Like::firstOrCreate(
                        [
                            'user_id' => $likerId,
                            'likeable_id' => $post->id,
                            'likeable_type' => Post::class
                        ],
                        [
                            'type' => 'post',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }
    }

    /**
     * Creates likes for comments from different users
     */
    private function createCommentLikes(): void {
        // Get all comments with their author IDs
        $comments = Comment::select('id', 'user_id')->get();

        // Get all user IDs dynamically
        $allUserIds = User::pluck('id')->toArray();

        foreach ($comments as $comment) {
            // Get IDs of users who aren't the comment author
            $likerIds = array_diff($allUserIds, [$comment->user_id]);

            // Randomly determine how many users will like this comment (0-2)
            $numLikers = rand(0, min(2, count($likerIds)));

            // If we'll have likers, select them randomly
            if ($numLikers > 0) {
                // Shuffle potential liker IDs to randomize selection
                shuffle($likerIds);
                $selectedLikers = array_slice($likerIds, 0, $numLikers);

                foreach ($selectedLikers as $likerId) {
                    // Use firstOrCreate to prevent duplicates efficiently
                    Like::firstOrCreate(
                        [
                            'user_id' => $likerId,
                            'likeable_id' => $comment->id,
                            'likeable_type' => Comment::class
                        ],
                        [
                            'type' => 'comment',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }
    }

    /**
     * Updates the likes_count specifically for posts
     */
    private function updatePostLikesCounts(): void {
        // Get all likes for posts
        $postLikesCollection = Like::where('likeable_type', Post::class)->select('likeable_id')->get();

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
     */
    private function updateCommentLikesCounts(): void {
        // Get all likes for comments
        $commentLikesCollection = Like::where('likeable_type', Comment::class)->select('likeable_id')->get();

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
