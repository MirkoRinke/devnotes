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
     * 
     * @example | $this->getAllowedPostValues();
     */
    protected function getAllowedPostValues(): array {
        return [
            'language' => [
                'HTML',
                'CSS',
                'SCSS',
                'JavaScript',
                'TypeScript',
                'PHP',
                'Python',
                'Shell',
                'SQL',
                'Java',
                'C#',
                'Go',
                'Rust',
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
                'Snippet',
                'Tutorial',
                'Feedback',
                'Showcase',
                'Question',
                'Resources'
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
                'Node.js',
                'NumPy',
                'SciPy',
                'Material UI',

            ],
            'status' => [
                'Draft',
                'Private',
                'Published',
                'Archived'
            ],
        ];
    }


    /**
     * Run the database seeds.
     */
    public function run(): void {
        $this->command->info('Seeding allowed values for post fields...');

        $allowedValues = $this->getAllowedPostValues();

        foreach ($allowedValues as $field => $values) {
            foreach ($values as $value) {
                PostAllowedValue::firstOrCreate([
                    'name' => $value,
                    'type' => $field,
                ]);
            }
            $this->command->info("Allowed values for field '{$field}' have been seeded.");
        }
    }
}
