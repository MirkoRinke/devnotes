<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Comment;

class CommentSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {

        // Main comment on Post 1 (Svelte Store)
        $comment1 = Comment::create([
            'post_id' => 1,
            'user_id' => 2,
            'parent_id' => null,
            'content' => 'Danke für diesen hilfreichen Beitrag zu Svelte Stores!',
            'depth' => 0,
        ]);

        // Reply to comment 1 by post author
        Comment::create([
            'post_id' => 1,
            'user_id' => 1,
            'parent_id' => $comment1->id,
            'content' => 'Freut mich, dass es dir gefällt! Gibt es noch andere Themen zu Svelte, die dich interessieren?',
            'depth' => 1,
        ]);

        // Main comment on Post 2 (Eloquent)
        $comment2 = Comment::create([
            'post_id' => 2,
            'user_id' => 3,
            'parent_id' => null,
            'content' => 'Eloquent ist wirklich eines der besten ORMs für PHP!',
            'depth' => 0,
        ]);

        // First reply to comment 2
        Comment::create([
            'post_id' => 2,
            'user_id' => 2,
            'parent_id' => $comment2->id,
            'content' => 'Absolut, ich nutze es in all meinen Laravel-Projekten.',
            'depth' => 1,
        ]);

        // Second reply to comment 2 from post author
        Comment::create([
            'post_id' => 2,
            'user_id' => 1,
            'parent_id' => $comment2->id,
            'content' => 'Gibt es Verbesserungsvorschläge für diesen Beitrag?',
            'depth' => 1,
        ]);

        // Main comment on Post 3 (Vue)
        Comment::create([
            'post_id' => 3,
            'user_id' => 2,
            'parent_id' => null,
            'content' => 'Die Composition API hat Vue wirklich auf ein neues Level gebracht!',
            'depth' => 0,
        ]);

        // Main comment on Post 5 (Express.js)
        $comment3 = Comment::create([
            'post_id' => 5,
            'user_id' => 3,
            'parent_id' => null,
            'content' => 'Ich arbeite täglich mit Express.js, super Framework!',
            'depth' => 0,
        ]);

        // First reply to comment 3
        $reply1 = Comment::create([
            'post_id' => 5,
            'user_id' => 2,
            'parent_id' => $comment3->id,
            'content' => 'Was sind deine Lieblings-Middleware-Pakete für Express?',
            'depth' => 1,
        ]);

        // Nested reply to reply1 (depth 2 comment)
        Comment::create([
            'post_id' => 5,
            'user_id' => 3,
            'parent_id' => $reply1->id,
            'content' => 'Ich nutze hauptsächlich body-parser, cors und helmet für Sicherheit.',
            'depth' => 2,
        ]);

        // Main comment on Post 6 (Docker)
        Comment::create([
            'post_id' => 6,
            'user_id' => 1,
            'parent_id' => null,
            'content' => 'Docker hat meine Entwicklungsumgebung revolutioniert!',
            'depth' => 0,
        ]);

        // Main comment on Post 8 (Python Data Science)
        $comment4 = Comment::create([
            'post_id' => 8,
            'user_id' => 1,
            'parent_id' => null,
            'content' => 'Python ist meine bevorzugte Sprache für Data Science. Pandas ist unverzichtbar!',
            'depth' => 0,
        ]);

        // Reply to comment 4
        Comment::create([
            'post_id' => 8,
            'user_id' => 3,
            'parent_id' => $comment4->id,
            'content' => 'Stimme zu! Hast du schon NumPy und Matplotlib ausprobiert?',
            'depth' => 1,
        ]);

        // Deleted comment on Post 9 (AWS)
        Comment::create([
            'post_id' => 9,
            'user_id' => 2,
            'parent_id' => null,
            'content' => 'Dieser Kommentar wurde gelöscht.',
            'is_deleted' => true,
            'depth' => 0,
        ]);

        // Main comment on Post 10 (GraphQL)
        Comment::create([
            'post_id' => 10,
            'user_id' => 1,
            'parent_id' => null,
            'content' => 'GraphQL ist besonders für Frontend-Entwickler ein Segen!',
            'depth' => 0,
        ]);

        // Reply to comment 10
        Comment::create([
            'post_id' => 4,
            'user_id' => 3,
            'parent_id' => null,
            'content' => 'Functional Components sind viel übersichtlicher als Class Components.',
            'depth' => 0,
        ]);
    }
}
