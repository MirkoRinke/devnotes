<?php

namespace Database\Factories;

use App\Models\PostAllowedValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Traits\PostAttributeRelationManager;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory {

    /**
     *  The traits used in the Factory
     */
    use PostAttributeRelationManager;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
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
            'category' => fake()->randomElement([
                'Frontend',
                'Backend',
                'Fullstack',
                'DevOps',
                'Data Science',
                'Machine Learning',
                'Game Development',
                'Cloud Computing'
            ]),
            'post_type' => fake()->randomElement([
                'Snippet',
                'Tutorial',
                'Feedback',
                'Showcase',
                'Question'
            ]),
            'status' => fake()->randomElement([
                'Draft',
                'Private',
                'Published',
                'Archived'
            ]),
            'history' => [],
            'moderation_info' => [],
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure() {
        return $this->afterCreating(function ($post) {
            $tagNames = fake()->randomElement([
                ['Laravel', 'PHP', 'Backend'],
                ['JavaScript', 'React', 'Frontend'],
                ['Python', 'Data Science', 'Machine Learning'],
                ['DevOps', 'Docker', 'Kubernetes'],
                ['Game Development', 'Unity', 'C#'],
            ]);

            $languageNames = fake()->randomElement([
                ['PHP', 'JavaScript'],
                ['Python', 'SQL'],
                ['Java', 'C#', 'TypeScript'],
                ['Go', 'Rust'],
                ['HTML', 'CSS', 'JavaScript'],
            ]);

            $technologyNames = fake()->randomElement([
                ['Angular', 'React', 'Vue'],
                ['Laravel', 'Django', 'Flask'],
                ['Spring', 'Express', 'Node.js'],
                ['Pandas', 'NumPy', 'SciPy'],
                ['Bootstrap', 'TailwindCSS', 'Material UI'],
            ]);

            // Create a placeholder user for system relations
            $systemUser = (object)[
                'role' => 'system',
                'id' => 2
            ];

            $this->syncMultipleRelations($post, $systemUser, [
                'tags' => $tagNames,
                'languages' => $languageNames,
                'technologies' => $technologyNames
            ]);
        });
    }
}
