<?php

namespace Database\Factories;

use App\Models\PostAllowedValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Traits\PostAttributeRelationManager;
use App\Traits\PostAllowedValueHelper;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory {

    /**
     *  The traits used in the Factory
     */
    use PostAttributeRelationManager, PostAllowedValueHelper;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        static $categories = null;
        static $postTypes = null;
        static $statuses = null;

        if ($categories === null || $postTypes === null || $statuses === null) {
            $categories = PostAllowedValue::where('type', 'category')->pluck('name')->toArray();
            $postTypes = PostAllowedValue::where('type', 'post_type')->pluck('name')->toArray();
            $statuses = PostAllowedValue::where('type', 'status')->pluck('name')->toArray();
        }

        $createdAt = fake()->dateTimeBetween('-1 year', 'now');

        $updatedAt = fake()->optional(0.7, $createdAt)->dateTimeBetween($createdAt, 'now');


        return [
            'user_id' => User::whereNotIn('role', ['system'])->inRandomOrder()->first()?->id ?? User::factory(),
            'title' => fake()->sentence(6, true),
            'code' => fake()->text(300),
            'description' => fake()->paragraph(3, true),
            'images' => fake()->randomElement([
                [
                    "https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300",
                    "https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300"
                ],
                ["https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300"],
                [
                    "https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300",
                    "https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300",
                    "https://picsum.photos/id/" . fake()->numberBetween(1, 1000) . "/200/300"
                ],
            ]),
            'videos' => fake()->randomElement([
                ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ]),
            'resources' => fake()->randomElement([
                ['https://laravel.com/docs/master'],
                ['https://angular.dev', 'https://vuejs.org/guide/introduction'],
                ['https://www.php.net/manual/de/index.php', 'https://developer.mozilla.org/de/docs/Web/JavaScript/Guide/Introduction', 'https://developer.mozilla.org/de/docs/Web/CSS'],
            ]),
            'external_source_previews' => function (array $attributes) {
                $images = $attributes['images'] ?? [];
                $videos = $attributes['videos'] ?? [];
                $resources = $attributes['resources'] ?? [];

                $previews = [];

                foreach ($images as $url) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    $previews[] = [
                        'url' => $url,
                        'type' => 'images',
                        'domain' => $domain
                    ];
                }

                foreach ($videos as $url) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    $previews[] = [
                        'url' => $url,
                        'type' => 'videos',
                        'domain' => $domain
                    ];
                }

                foreach ($resources as $url) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    $previews[] = [
                        'url' => $url,
                        'type' => 'resources',
                        'domain' => $domain
                    ];
                }

                return $previews;
            },
            'category' => fake()->randomElement($categories),
            'post_type' => fake()->randomElement($postTypes),
            'status' => fake()->randomElement($statuses),
            'syntax_highlighting' => null,
            'history' => [],
            'moderation_info' => [],
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure() {
        return $this->afterCreating(function ($post) {
            static $tags = null;
            static $languages = null;
            static $technologies = null;

            if ($tags === null || $languages === null || $technologies === null) {
                $tags = PostAllowedValue::where('type', 'tag')->pluck('name')->toArray();
                $languages = PostAllowedValue::where('type', 'language')->pluck('name')->toArray();
                $technologies = PostAllowedValue::where('type', 'technology')->pluck('name')->toArray();
            }

            $tagNames = fake()->randomElements($tags, fake()->numberBetween(1, 5));
            $languageNames = fake()->randomElements($languages, fake()->numberBetween(1, 5));
            $technologyNames = fake()->randomElements($technologies, fake()->numberBetween(1, 5));

            // Create a placeholder user for system relations
            $systemUser = (object)[
                'role' => 'system',
                'id' => 2
            ];

            $this->syncMultipleRelations($post, $systemUser, [
                'tag' => $tagNames,
                'language' => $languageNames,
                'technology' => $technologyNames
            ]);

            if (!empty($languageNames)) {
                $post->syntax_highlighting = $languageNames[0];
                $post->save();
            }

            $postAllowedValueMap = ["category", "post_type", "status", "tag", "language", "technology"];

            foreach ($postAllowedValueMap as $value) {
                if (isset($post->$value) && ($value == 'category' || $value == 'post_type' || $value == 'status')) {
                    $this->updatePostAllowedValueCount($post->$value, $value, 'increment');
                } else if ($value == 'tag' || $value == 'language' || $value == 'technology') {
                    $this->updatePostAllowedValueCount(${$value . 'Names'}, $value, 'increment');
                }
            }
        });
    }
}
