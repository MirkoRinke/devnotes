<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\UserFollower;

trait PostHelper {

    /**
     * Generate external source previews for post data
     * 
     * @param array $validatedData The validated post data
     * @param Post|null $existingPost Existing post for fallback values (null for creation)
     * @return array The generated external source previews
     */
    protected function generateExternalSourcePreviews(array $validatedData, ?Post $existingPost = null): array {
        if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData) || array_key_exists('videos', $validatedData)) {
            $externalSourcePreviews = $this->externalSourceService->generatePreviews([
                'images' => $validatedData['images'] ?? $existingPost?->images ?? [],
                'videos' => $validatedData['videos'] ?? $existingPost?->videos ?? [],
                'resources' => $validatedData['resources'] ?? $existingPost?->resources ?? []
            ]);

            return $externalSourcePreviews;
        }

        return $existingPost->external_source_previews ?? [];
    }


    /**
     * If the posts belong to a single user and the authenticated user follows that user,
     * update the last_posts_visited_at timestamp for that follower relationship.
     * 
     * @param \Illuminate\Support\Collection $posts
     * @param \App\Models\User|null $user
     * 
     * @return void
     * 
     * @example | $this->setLastPostVisitedIfFollowing($posts, $user)
     */
    protected function setLastPostVisitedIfFollowing($posts, $user) {
        if (!$user) {
            return;
        }

        $postsUserIds = $posts->pluck('user_id')->unique()->toArray();

        if (count($postsUserIds) === 1) {
            UserFollower::where('user_id', $postsUserIds[0])
                ->where('follower_id', $user->id)
                ->update(['last_posts_visited_at' => now()]);
        }
    }
}
