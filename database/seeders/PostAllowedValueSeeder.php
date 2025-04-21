<?php

namespace Database\Seeders;

use App\Models\PostAllowedValue;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostAllowedValueSeeder extends Seeder {

    /**
     * Get allowed values for post fields
     * 
     * @return array
     */
    protected function getAllowedPostValues(): array {
        return [
            'language' => [
                'HTML',
                'CSS',
                'SCSS',
                'JavaScript',
                'Typescript',
                'PHP',
                'Python',
                'Shell'
            ],
            'category' => [
                'Frontend',
                'Backend',
                'Fullstack',
                'DevOps',
                'Data Science',
                'Machine Learning',
                'Game Development',
                'Cloud Computing'
            ],
            'post_type' => [
                'snippet',
                'tutorial',
                'feedback',
                'showcase',
                'question'
            ],
            'technology' => [
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
                'Node.js'

            ],
            'status' => [
                'draft',
                'private',
                'published',
                'archived'
            ],
        ];
    }


    /**
     * Run the database seeds.
     */
    public function run(): void {

        $allowedValues = $this->getAllowedPostValues();

        foreach ($allowedValues as $field => $values) {
            foreach ($values as $value) {
                PostAllowedValue::create([
                    'name' => $value,
                    'type' => $field,
                ]);
            }
        }
    }
}
