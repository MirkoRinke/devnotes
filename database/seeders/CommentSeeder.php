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
     * Run the database seeds.
     */
    public function run(): void {

        $comments = Comment::factory()->count(200)->create();
        foreach ($comments as $comment) {
            $this->commentRelationService->updateLastCommentAt($comment);
            $this->commentRelationService->updateCommentsCount($comment, 'increment');
        }


        $replyComments = Comment::factory()->count(75)->reply()->create();
        foreach ($replyComments as $reply) {
            $this->commentRelationService->updateLastCommentAt($reply);
            $this->commentRelationService->updateCommentsCount($reply, 'increment');
        }


        $replyToReplyComments = Comment::factory()->count(50)->replyToReply()->create();
        foreach ($replyToReplyComments as $replyToReply) {
            $this->commentRelationService->updateLastCommentAt($replyToReply);
            $this->commentRelationService->updateCommentsCount($replyToReply, 'increment');
        }


        $deletedComments = Comment::factory()->count(40)->deleted()->create();
        foreach ($deletedComments as $deleted) {
            $this->commentRelationService->updateLastCommentAt($deleted);
            $this->commentRelationService->updateCommentsCount($deleted, 'increment');
        }
    }
}
