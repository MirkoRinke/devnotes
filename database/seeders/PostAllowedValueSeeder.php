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
            'category' => [
                'frontend',
                'backend',
                'fullstack',
                'devops',
                'data science',
                'machine learning',
                'game development',
                'cloud computing'
            ],
            'post_type' => [
                'snippets',
                'tutorials',
                'feedback',
                'showcase',
                'questions',
                'resources'
            ],
            'status' => [
                'draft',
                'private',
                'published',
                'archived'
            ],
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
                'Angular',
                'Laravel',
                'Django',
                'Spring',
                'Express',
                'React',
                'Vue',
                'Svelte',
                'jQuery',
                'Next',
                'Vite',
                'Webpack',
                'Flask',
            ],
            'technology' => [
                'Docker',
                'Git',
                'GitHub',
                'GitLab',
                'Jenkins',
                'Kubernetes',
                'AWS',
                'Azure',
                'Firebase',
                'Heroku',
                'Netlify',
                'Vercel',
                'Nginx',
                'Apache',
                'Postman',
                'Figma',
                'Jira',
                'Trello',
                'Slack',
                'Notion',
            ],
            'tag' => [
                'Web Development',
                'Mobile Development',
                'Game Development',
                'Data Analysis',
                'Machine Learning',
                'DevOps',
                'Cloud Computing',
                'Open Source',
                'Community',
                'AI',
                'Blockchain',
                'Cybersecurity',
                'Software Engineering'
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

            /**
             * Ensure that the values are definitely lowercase for fields like 'category', 'post_type', and 'status'.
             */
            $convertToLower = in_array($field, ['category', 'post_type', 'status']);
            if ($convertToLower) {
                $values = array_map('strtolower', $values);
            }

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
