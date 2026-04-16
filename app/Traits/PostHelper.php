<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\PostRead;
use App\Models\UserFollower;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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


            return $externalSourcePreviews ?? [];
        }

        return $existingPost?->external_source_previews ?? [];
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


    /**
     * Mark a Post as Read for the Authenticated User
     * 
     * This method records that a user has read a specific post by updating or creating
     * a record in the post_reads table. If the user is not authenticated, the method
     * simply returns without performing any action.
     *
     * @param Post $post The post that has been read.
     * @param mixed $user The authenticated user who read the post.
     * 
     * @return void
     * 
     * @example | $this->markPostAsRead($post, $user);
     */
    protected function markPostAsRead(Post $post, $user): void {
        if (!$user) {
            return;
        }

        PostRead::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $user->id],
            ['updated_at' => now()]
        );
    }



    /**
     * Determine if the authenticated user has read the posts in the query.
     * 
     * This method checks if the authenticated user has read each post in the provided query.
     * It adds an 'is_read' attribute to each post, indicating whether the user has read it.
     * If the request does not specify a 'select' parameter or if 'is_read' is included in
     * the original select fields, the method performs the check. Otherwise, it returns the
     * query unchanged.
     * 
     * @param Request $request The incoming HTTP request.
     * @param mixed $user The authenticated user (or null if not authenticated).
     * @param Builder|Collection|LengthAwarePaginator|Post $query The query or collection of posts to check.
     * @param array $originalSelectFields The original fields requested in the 'select' parameter.
     * 
     * @return Builder|Collection|LengthAwarePaginator|Post The modified query or collection with 'is_read' attributes added.
     * 
     * @example | $posts = $this->isRead($request, $user, $posts, $originalSelectFields);
     */
    public function isRead(Request $request, $user, $query, $originalSelectFields): Builder|Collection|LengthAwarePaginator|Post {
        /**
         * If the request does not have 'select' or if 'is_read' is included in 'select',
         * we will check if the authenticated user has read the post in the query.
         * and we will add the 'is_read' field to the post relation.
         */
        if (!$request->has('select') || in_array('is_read', $originalSelectFields)) {
            if ($query instanceof Post) {
                if (!$user) {
                    $query->is_read = false;
                    return $query;
                }
                $query->is_read = $user->postReads()->where('post_id', $query->id)->exists();

                return $query;
            }

            $postIds = $query->pluck('id')->toArray();
            $readPosts = [];

            if ($user && !empty($postIds)) {
                $readPosts = $user->postReads()->whereIn('post_id', $postIds)->pluck('post_id')->toArray();
            }

            $query->each(function ($post) use ($readPosts) {
                $post->is_read = in_array($post->id, $readPosts);
            });

            return $query;
        }
        return $query;
    }
}
