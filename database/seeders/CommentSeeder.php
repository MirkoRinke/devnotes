<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Comment;

use App\Services\CommentRelationService;
use Illuminate\Support\Facades\DB;

class CommentSeeder extends Seeder {

    /**
     *  The Service used in the Seeder
     */
    protected $commentRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(CommentRelationService $commentRelationService) {
        $this->commentRelationService = $commentRelationService;
    }

    /**
     * Create a comment and update the metadata
     *
     * @param array $data
     * @return Comment
     * 
     * @example | $this->createCommentWithMetadata($data);
     */
    private function createCommentWithMetadata(array $data) {
        return DB::transaction(function () use ($data) {
            $comment = new Comment();

            $comment->content = $data['content'];
            $comment->post_id = $data['post_id'];
            $comment->parent_id = $data['parent_id'] ?? null;
            $comment->user_id = $data['user_id'] ?? null;
            $comment->parent_content = $data['parent_content'] ?? null;
            $comment->is_deleted = $data['is_deleted'] ?? false;
            $comment->depth = $data['depth'] ?? 0;

            $comment->save();

            $this->commentRelationService->updateLastCommentAt($comment);
            $this->commentRelationService->updateCommentsCount($comment, 'increment');

            return $comment;
        });
    }

    /**
     * Run the database seeds.
     */
    public function run(): void {
        // Main comment on Post 1 (Svelte Store)
        $comment1 = $this->createCommentWithMetadata([
            'post_id' => 1,
            'user_id' => 4,
            'parent_id' => null,
            'content' => 'Danke für diesen hilfreichen Beitrag zu Svelte Stores!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // Reply to comment 1 by post author
        $this->createCommentWithMetadata([
            'post_id' => 1,
            'user_id' => 7,
            'parent_id' => $comment1->id,
            'content' => 'Freut mich, dass es dir gefällt! Gibt es noch andere Themen zu Svelte, die dich interessieren?',
            'parent_content' => $comment1->content,
            'depth' => 1,
        ]);

        // Main comment on Post 2 (Eloquent)
        $comment2 = $this->createCommentWithMetadata([
            'post_id' => 2,
            'user_id' => 9,
            'parent_id' => null,
            'content' => 'Eloquent ist wirklich eines der besten ORMs für PHP!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // First reply to comment 2
        $this->createCommentWithMetadata([
            'post_id' => 2,
            'user_id' => 4,
            'parent_id' => $comment2->id,
            'content' => 'Absolut, ich nutze es in all meinen Laravel-Projekten.',
            'parent_content' => $comment2->content,
            'depth' => 1,
        ]);

        // Second reply to comment 2 from post author
        $this->createCommentWithMetadata([
            'post_id' => 2,
            'user_id' => 7,
            'parent_id' => $comment2->id,
            'content' => 'Gibt es Verbesserungsvorschläge für diesen Beitrag?',
            'parent_content' => $comment2->content,
            'depth' => 1,
        ]);

        // Main comment on Post 3 (Vue)
        $this->createCommentWithMetadata([
            'post_id' => 3,
            'user_id' => 4,
            'parent_id' => null,
            'content' => 'Die Composition API hat Vue wirklich auf ein neues Level gebracht!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // Main comment on Post 5 (Express.js)
        $comment3 = $this->createCommentWithMetadata([
            'post_id' => 5,
            'user_id' => 9,
            'parent_id' => null,
            'content' => 'Ich arbeite täglich mit Express.js, super Framework!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // First reply to comment 3
        $reply1 = $this->createCommentWithMetadata([
            'post_id' => 5,
            'user_id' => 4,
            'parent_id' => $comment3->id,
            'content' => 'Was sind deine Lieblings-Middleware-Pakete für Express?',
            'parent_content' => $comment3->content,
            'depth' => 1,
        ]);

        // Main comment on Post 6 (Docker)
        $this->createCommentWithMetadata([
            'post_id' => 6,
            'user_id' => 7,
            'parent_id' => null,
            'content' => 'Docker hat meine Entwicklungsumgebung revolutioniert!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // Main comment on Post 8 (Python Data Science)
        $comment4 = $this->createCommentWithMetadata([
            'post_id' => 8,
            'user_id' => 7,
            'parent_id' => null,
            'content' => 'Python ist meine bevorzugte Sprache für Data Science. Pandas ist unverzichtbar!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // Reply to comment 4
        $this->createCommentWithMetadata([
            'post_id' => 8,
            'user_id' => 9,
            'parent_id' => $comment4->id,
            'content' => 'Stimme zu! Hast du schon NumPy und Matplotlib ausprobiert?',
            'parent_content' => $comment4->content,
            'depth' => 1,
        ]);

        // Deleted comment on Post 9 (AWS)
        $this->createCommentWithMetadata([
            'post_id' => 9,
            'user_id' => 4,
            'parent_id' => null,
            'content' => 'Content deleted (Seeder)',
            'parent_content' => null,
            'is_deleted' => true,
            'depth' => 0,
        ]);

        // Main comment on Post 10 (GraphQL)
        $this->createCommentWithMetadata([
            'post_id' => 10,
            'user_id' => 7,
            'parent_id' => null,
            'content' => 'GraphQL ist besonders für Frontend-Entwickler ein Segen!',
            'parent_content' => null,
            'depth' => 0,
        ]);

        // Comment on Post 4 (React)
        $this->createCommentWithMetadata([
            'post_id' => 4,
            'user_id' => 9,
            'parent_id' => null,
            'content' => 'Functional Components sind viel übersichtlicher als Class Components.',
            'parent_content' => null,
            'depth' => 0,
        ]);
    }
}
