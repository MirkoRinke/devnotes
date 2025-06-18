<?php

namespace App\Services;

use App\Models\Post;
use App\Models\UserLike;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

use App\Services\CommentRelationService;

/**
 * This PostRelationService handles the deletion of comments, likes, and reports associated with a post.
 * It ensures that all related data is cleaned up when a post is deleted or modified.
 */
class PostRelationService {

    /**
     * The services used in the controller
     */
    protected $commentRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(CommentRelationService $commentRelationService) {
        $this->commentRelationService = $commentRelationService;
    }


    /**
     * Delete all comments associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted comments
     * 
     * @example | $this->postRelationService->deleteComments($post);
     */
    public function deleteComments(Post $post): int {
        $totalDeleted = 0;
        $commentIds = [];

        // Get all comments associated with the post and delete their likes and reports
        $post->comments()->chunkById(100, function ($comments) use (&$commentIds) {
            foreach ($comments as $comment) {
                $commentIds[] = $comment->id;
                $this->commentRelationService->deleteReports($comment);
                $this->commentRelationService->deleteLikes($comment);
            }
        });

        // Delete all comments in chunks to avoid memory issues and to ensure that the database can handle the load
        if (!empty($commentIds)) {
            foreach (array_chunk($commentIds, 100) as $chunk) {
                $deleted = DB::table('comments')->whereIn('id', $chunk)->delete();
                $totalDeleted += $deleted;
            }
        }
        return $totalDeleted;
    }


    /**
     * Delete all reports associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted reports
     * 
     * @example | $this->postRelationService->deleteReports($post);
     */
    public function deleteReports(Post $post): int {
        $totalDeleted = 0;

        UserReport::where('reportable_type', Post::class)
            ->where('reportable_id', $post->id)
            ->chunkById(100, function ($reports) use (&$totalDeleted) {
                $deleted = DB::table('user_reports')
                    ->whereIn('id', $reports->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }

    /**
     * Delete all likes associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted likes
     * 
     * @example | $this->postRelationService->deleteLikes($post);
     */
    public function deleteLikes(Post $post): int {
        $totalDeleted = 0;

        UserLike::where('likeable_type', Post::class)
            ->where('likeable_id', $post->id)
            ->chunkById(100, function ($likes) use (&$totalDeleted) {
                $deleted = DB::table('user_likes')
                    ->whereIn('id', $likes->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }


    /**
     * Check if a post is favorited by a user
     *
     * @param User|null $user
     * @param Builder|Collection|LengthAwarePaginator|Post $query
     * @return Builder|Collection|LengthAwarePaginator|Post
     * 
     * @example | $this->postRelationService->isPostFavorited($user, $query);
     */
    public function isPostFavorited($user, $query): Builder|Collection|LengthAwarePaginator|Post {
        if ($query instanceof Post) {
            if (!$user) {
                $query->is_favorited = false;
                return $query;
            }
            $query->is_favorited = $user->favorites()
                ->where('post_id', $query->id)
                ->exists();
            return $query;
        }

        $postIds = $query->pluck('id')->toArray();
        $favoritedPosts = [];

        if ($user) {
            $favoritedPosts = $user->favorites()
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->toArray();
        }

        $query->each(function ($post) use ($favoritedPosts) {
            $post->is_favorited = in_array($post->id, $favoritedPosts);
        });

        return $query;
    }
}
