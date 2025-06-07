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

        $firstComments = 500;
        $commentsWithReply = 150;
        $commentsWithReplyToReply = 100;
        $deletedComments = 20;
        $progressInterval = 10;


        /**
         * Create first comments
         */
        for ($i = 0; $i < $firstComments; $i++) {
            $comments = Comment::factory()->count(1)->create();
            foreach ($comments as $comment) {
                $this->commentRelationService->updateLastCommentAt($comment);
                $this->commentRelationService->updateCommentsCount($comment, 'increment');
            }
            if ($i % $progressInterval == 0) {
                $this->command->info('Comments created successfully! ' . ($progressInterval + $i));
            }
        }


        /**
         * Create reply comments, 
         */
        for ($i = 0; $i < $commentsWithReply; $i++) {
            $comments = Comment::factory()->count(1)->reply()->create();
            foreach ($comments as $reply) {
                $this->commentRelationService->updateLastCommentAt($reply);
                $this->commentRelationService->updateCommentsCount($reply, 'increment');
            }
            if ($i % $progressInterval == 0) {
                $this->command->info('Reply comments created successfully! ' . ($progressInterval + $i));
            }
        }


        /**
         * Create reply to reply comments
         */
        for ($i = 0; $i < $commentsWithReplyToReply; $i++) {
            $comments = Comment::factory()->count(1)->replyToReply()->create();
            foreach ($comments as $replyToReply) {
                $this->commentRelationService->updateLastCommentAt($replyToReply);
                $this->commentRelationService->updateCommentsCount($replyToReply, 'increment');
            }
            if ($i % $progressInterval == 0) {
                $this->command->info('Reply to reply comments created successfully! ' . ($progressInterval + $i));
            }
        }


        /**
         * Create deleted comments
         */
        for ($i = 0; $i < $deletedComments; $i++) {
            $comments = Comment::factory()->count(1)->deleted()->create();
            foreach ($comments as $deleted) {
                $this->commentRelationService->updateLastCommentAt($deleted);
                $this->commentRelationService->updateCommentsCount($deleted, 'increment');
            }
            if ($i % $progressInterval == 0) {
                $this->command->info('Deleted comments created successfully! ' . ($progressInterval + $i));
            }
        }
    }
}
