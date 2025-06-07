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
        $this->command->info('Seeding comments...');

        $multiplier = (int) 1;

        $firstComments = (int) 500;
        $commentsWithReply = (int) 150;
        $commentsWithReplyToReply = (int) 100;
        $deletedComments = (int) 20;

        /**
         * Create first comments
         */
        for ($i = 0; $i < $multiplier; $i++) {
            $comments = Comment::factory()->count($firstComments)->create();
            foreach ($comments as $comment) {
                $this->commentRelationService->updateLastCommentAt($comment);
                $this->commentRelationService->updateCommentsCount($comment, 'increment');
            }
            $this->command->info("Created " . ($firstComments * $multiplier) . " comments. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }


        /**
         * Create reply comments, 
         */
        for ($i = 0; $i < $multiplier; $i++) {
            $comments = Comment::factory()->count($commentsWithReply)->reply()->create();
            foreach ($comments as $reply) {
                $this->commentRelationService->updateLastCommentAt($reply);
                $this->commentRelationService->updateCommentsCount($reply, 'increment');
            }
            $this->command->info("Created " . ($commentsWithReply * $multiplier) . " reply comments. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }


        /**
         * Create reply to reply comments
         */
        for ($i = 0; $i < $multiplier; $i++) {
            $comments = Comment::factory()->count($commentsWithReplyToReply)->replyToReply()->create();
            foreach ($comments as $replyToReply) {
                $this->commentRelationService->updateLastCommentAt($replyToReply);
                $this->commentRelationService->updateCommentsCount($replyToReply, 'increment');
            }
            $this->command->info("Created " . ($commentsWithReplyToReply * $multiplier) . " reply to reply comments. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }


        /**
         * Create deleted comments
         */
        for ($i = 0; $i < $multiplier; $i++) {
            $comments = Comment::factory()->count($deletedComments)->deleted()->create();
            foreach ($comments as $deleted) {
                $this->commentRelationService->updateLastCommentAt($deleted);
                $this->commentRelationService->updateCommentsCount($deleted, 'increment');
            }
            $this->command->info("Created " . ($deletedComments * $multiplier) . " deleted comments. Progress: " . (($i + 1) * 100 / $multiplier) . "%");
        }
    }
}
