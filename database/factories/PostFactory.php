<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::inRandomOrder()->first()->id,
            'title' => fake()->sentence(6, true),
            'code' => fake()->text(1000),
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
            'language' => fake()->randomElement([
                'HTML',
                'CSS',
                'SCSS',
                'JavaScript',
                'TypeScript',
                'PHP',
                'Python',
                'Shell'
            ]),
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
                'snippet',
                'tutorial',
                'feedback',
                'showcase',
                'question'
            ]),
            'technology' => fake()->randomElement([
                'Angular',
                'Laravel',
                'Django',
                'Spring',
                'Express',
                'React',
                'Vue',
                'Svelte',
                'jQuery',
                'Pandas',
                'Bootstrap',
                'TailwindCSS',
                'Redux',
                'Next.js',
                'Vite',
                'Webpack',
                'Flask',
                'Node.js',
            ]),
            'tags' => fake()->randomElement([
                ['laravel', 'php', 'backend'],
                ['javascript', 'react', 'frontend'],
                ['python', 'data science', 'machine learning'],
                ['devops', 'docker', 'kubernetes'],
                ['game development', 'unity', 'c#'],
            ]),
            'status' => fake()->randomElement([
                'draft',
                'private',
                'published',
                'archived'
            ]),
            'history' => [],
        ];
    }
}
