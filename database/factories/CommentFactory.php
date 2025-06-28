<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory {

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'content' => fake()->paragraph(rand(1, 3)),
            'post_id' => Post::inRandomOrder()->first()?->id ?? Post::factory(),
            'user_id' => User::whereNotIn('role', ['system'])->inRandomOrder()->first()?->id ?? User::factory(),
            'parent_id' => null,
            'parent_content' => null,
            'is_deleted' => false,
            'depth' => 0,
            'moderation_info' => [],
        ];
    }


    /**
     * Configure the factory to create a reply to another comment.
     *
     * @param Comment|null $parentComment Parent comment to reply to
     * @return static
     */
    public function reply(): static {
        return $this->state(function (array $attributes) {
            $parentComment = Comment::factory()->create();
            return [
                'parent_id' => $parentComment->id,
                'post_id' => $parentComment->post_id,
                'parent_content' => $parentComment->content,
                'depth' => $parentComment->depth + 1,
            ];
        });
    }

    /**
     * Configure the factory to create a reply to a reply (depth=2).
     *
     * @return static
     */
    public function replyToReply(): static {
        return $this->state(function (array $attributes) {
            // First create a comment (depth=0)
            $originalComment = Comment::factory()->create();

            // Then create a reply to it (depth=1)
            $parentComment = Comment::factory()->state([
                'parent_id' => $originalComment->id,
                'post_id' => $originalComment->post_id,
                'parent_content' => $originalComment->content,
                'depth' => 1,
            ])->create();

            // Finally return data for reply to the reply (depth=2)
            return [
                'parent_id' => $parentComment->id,
                'post_id' => $parentComment->post_id,
                'parent_content' => $parentComment->content,
                'depth' => 2,
            ];
        });
    }

    /**
     * Configure the factory to create a deleted comment.
     *
     * @return static
     */
    public function deleted(): static {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => 3, // Assuming user with ID 3 is the System Deleted User
                'content' => 'Content deleted (Factory)',
                'is_deleted' => true,
            ];
        });
    }
}
