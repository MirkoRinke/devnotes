<?php

namespace Database\Seeders;

use App\Models\PostAllowedValue;
use App\Traits\PostAllowedValueHelper;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostAllowedValueSeeder extends Seeder {

    use PostAllowedValueHelper;

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
                // Core Development Areas
                'Frontend Development',
                'Backend Development',
                'Fullstack Development',
                'Mobile Development',
                'Game Development',

                // Infrastructure & Operations
                'DevOps & Cloud',
                'Database & Storage',
                'Testing & QA',
                'Monitoring & Observability',
                'Tooling & Build Systems',

                // Specialized Domains
                'Data Science & AI',
                'Cybersecurity',
                'Web3 & Blockchain',
                'Graphics & 3D',
                'IoT & Embedded',

                // General & Conceptual
                'UI/UX Design',
                'Algorithms & Data Structures',
                'Product Management',
                'Career Advice',
                'Open Source',
                'Other',
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
                //! A. JavaScript / TypeScript Ecosystem

                // Core Runtimes & Languages
                'JavaScript',
                'TypeScript',
                'Node.js',

                // Frontend Frameworks
                'React',
                'Vue.js',
                'Svelte',
                'Angular',
                'SolidJS',
                'Qwik',

                // Full-Stack Frameworks
                'Next.js',
                'Nuxt',
                'Remix',
                'Gatsby',
                'Astro',

                // Backend & API
                'Express',
                'NestJS',
                'Fastify',
                'TRPC',
                'GraphQL',

                // Libraries
                'jQuery',
                'RxJS',
                'Axios',
                'Lodash',
                'Socket.io',
                'Chart.js',
                'D3.js',
                'Three.js',
                'Swiper',

                // Authentication
                'Passport',

                // ORM/DB
                'Prisma',
                'Sequelize',
                'Mongoose',

                //! B. Python Ecosystem
                // Core Language
                'Python',

                // Web Frameworks
                'Django',
                'Flask',
                'FastAPI',

                // Data Science & ML
                'NumPy',
                'Pandas',
                'Tensorflow',
                'Keras',
                'PyTorch',
                'Plotly',

                // ORM & Tools
                'SQLAlchemy',

                //! C. Java / JVM Ecosystem
                // Core Language
                'Java',

                // Frameworks
                'Spring',
                'SpringBoot',

                // Libraries & Tools
                'Hibernate',

                //! D. PHP Ecosystem
                // Core Language
                'PHP',

                // Frameworks
                'Laravel',
                'Symfony',
                'Lumen',
                'CodeIgniter',

                // ORM
                'Doctrine',

                //! E. .NET / C# Ecosystem
                'C#',
                'Blazor',
                'EF Core',

                //! F. Other Languages & Frameworks
                // Systems & Low-Level
                'C',
                'C++',
                'Rust',
                'Go',

                // Mobile Native
                'Swift',
                'Kotlin',
                'Dart',

                // Other Languages
                'Ruby',
                'Lua',
                'Elixir',
                'Solidity',

                // Cross-Platform Frameworks
                'Ruby on Rails',
                'Phoenix',
                'Fiber',
                'Flutter',
                'React Native',
                'Electron',
                'Tauri',
                'Inertia.js',

                //! G. Gaming & Game Engines
                // Game Engines & Frameworks
                'Bevy Engine',
                'GDScript',


                //! H. Styling, Markup & Data Formats
                // Core Markup & Styling
                'HTML',
                'CSS',
                'SCSS',
                'LESS',

                // CSS Frameworks
                'Bootstrap',
                'Bulma',
                'Foundation',
                'Materialize',
                'Tailwind',

                // Markup & Data Formats
                'XML',
                'YAML',
                'JSON',
                'Markdown',

                //! I. Database Query Languages
                'SQL',

                //! J. Scripting & Shell Languages
                'Bash/Shell',
            ],
            'technology' => [
                //! A. Cloud Providers & PaaS

                // Comprehensive Cloud Platforms
                'AWS',
                'Azure',
                'Google Cloud',

                // Deployment & PaaS (Platform as a Service)
                'Vercel',
                'Netlify',
                'Cloudflare',
                'DigitalOcean',
                'Heroku',

                // Backend-as-a-Service (BaaS)
                'Firebase',
                'Supabase',

                //! B. DevOps & Infrastructure (IaC, Orchestration)
                // Containerization & Orchestration
                'Docker',
                'Podman',
                'Kubernetes',
                'Helm',
                'Portainer',

                // Infrastructure as Code (IaC) & Automation
                'Terraform',
                'Packer',
                'Ansible',

                // Service Mesh & Discovery
                'Traefik Proxy',

                // Virtualization & Hypervisors
                'Proxmox',

                //! C. Web Servers & Proxies
                'Nginx',
                'Apache',
                'Tomcat',
                'uWSGI',

                //! D. Databases & Storage
                // Relational Databases (SQL)
                'MySQL',
                'PostgreSQL',
                'SQLite',
                'MS SQL Server',
                'Oracle',
                'Azure SQL',

                // NoSQL & Document Databases
                'MongoDB',
                'DynamoDB',
                'Neo4j',
                'ClickHouse',

                // In-Memory & Caching
                'Redis',
                'Memcached',
                'InfluxDB',

                // Database Tools
                'DBeaver',

                //! E. Build Tools, Runtimes & Package Managers
                // Build Tools & Bundlers
                'Vite',
                'Webpack',
                'ESBuild',
                'Rollup',
                'Babel',
                'PostCSS',

                // Package Managers
                'NPM',
                'PNPM',
                'Yarn',
                'Composer',
                'Homebrew',
                'NuGet',
                'Poetry',

                // Runtimes & Task Runners
                'Bun',
                'DenoJS',
                'V8',
                'WASM',
                'Gradle',
                'Maven',

                //! F. Testing & Quality Assurance & API Tools
                // End-to-End (E2E) & Integration Testing
                'Cypress',
                'Playwright',
                'Selenium',

                // Unit Testing Frameworks
                'Jest',
                'Vitest',
                'Mocha',
                'Jasmine',
                'PHPUnit',
                'Pytest',
                'Unittest',
                'RSpec',
                'JUnit',

                // API Development & Testing
                'Postman',
                'Insomnia',
                'Hoppscotch',
                'OpenAPI',
                'Swagger',
                'gRPC',

                // Quality & Coverage
                'ESLint',
                'SonarQube',
                'Codecov',

                //! G. Version Control & CI/CD
                // Version Control Systems (VCS)
                'Git',

                // Hosting & Clients
                'GitHub',
                'GitLab',
                'GitKraken',

                // Continuous Integration/Continuous Deployment (CI/CD)
                'GitHub Actions',
                'Jenkins',
                'Azure DevOps',
                'Bamboo',
                'CircleCI',

                //! H. Design, UI/UX & Collaboration
                // Design & Prototyping Tools
                'Figma',
                'Sketch',

                // Project & Team Collaboration
                'Jira',
                'Trello',
                'Notion',
                'Slack',
                'Asana',
                'ClickUp',
                'Confluence',

                //! I. Monitoring & Observability
                // Metric Collection & Visualization
                'Prometheus',
                'Grafana',
                'OpenTelemetry',

                // Error & APM (Application Performance Monitoring)
                'Sentry',
                'Datadog',
                'New Relic',
                'Jaeger Tracing',

                // Log Management (ELK Stack Components)
                'Elasticsearch',
                'Kibana',
                'Logstash',
                'Beats',
                'Splunk',

                //! J. Game Engines (Tools)
                'Unity',
                'Unreal Engine',
                'Godot',

                //! K. Graphics & Media Tools
                'Photoshop',
                'Illustrator',
                'Blender',
                'GIMP',
                'Inkscape',
                'Canva',
                'SVGO',

                //! L. Data Science & Analytics Tools
                'Jupyter',
                'Kaggle',
                'Scikit Learn',
                'Streamlit',

                //! M. IDEs & Code Editors
                // General & Multi-Purpose IDEs
                'Visual Studio',

                // JetBrains IDEs
                'CLion',
                'GoLand',
                'IntelliJ',
                'PyCharm',
                'Rider',
                'WebStorm',

                // Code Editors
                'VS Code',
                'VIM',
                'XCode',
                'Android Studio',
                'GitHub Codespaces',
                'Gitpod',
                'StackBlitz',
                'CodePen',

                //! N. CMS & BaaS Platforms

                // Content Management Systems (CMS)
                'WordPress',
                'WooCommerce',
                'Typo3',
                'Ghost',
                'Shopware',

                // Headless & Specific Platforms
                'Sanity',
                'Moodle',
                'Appwrite',

                //! O. Hardware, Robotics & IoT
                'Arduino',
                'Raspberry Pi',
                'ROS',

                //! P. Shell & Terminal Tools
                'Bash',
                'Zsh',
                'Oh My Zsh',
                'PowerShell',
                'SSH',
                'TMUX',

                //! Q. Graphics & Low-Level APIs
                'OpenGL',
                'Vulkan',
                'OpenCV',
                'SDL',

                //! R. Security & Identity
                'Okta',
                'Vault',

                //! S. Messaging & Brokerage
                'RabbitMQ',
                'NATS',

                //! U. Development Utilities
                'Ngrok',
                'PM2',
                'Nodemon',
                'Discord.js',
                'cPanel',
                'Mapbox',
                'Salesforce',
            ],
            'tag' => [
                //! A. General Concepts & Methodology
                'best-practices',
                'architecture',
                'design-patterns',
                'clean-code',
                'refactoring',
                'performance-optimization',
                'security',
                'accessibility-a11y',
                'monorepo',
                'microservices',
                'serverless',
                'functional-programming',
                'object-oriented-programming',
                'scalability',

                //! B. Development Process & Workflow
                'local-development',
                'dev-workflow',
                'ci-pipeline',
                'deployment',
                'testing',
                'e2e-testing',
                'unit-testing',
                'integration-testing',
                'observability',
                'logging',
                'monitoring',
                'dependency-management',

                //! C. Ecosystem & Stack Specific Details (Examples)
                'react-hooks',
                'nextjs-v14',
                'webpack-config',
                'docker-compose',
                'kubernetes-ingress',
                'aws-lambda',
                'mysql-optimization',
                'typescript-migration',
                'real-time',
                'web-workers',
                'server-side-rendering',

                //! D. Career & Community
                'career-advice',
                'interview-prep',
                'open-source-contribution',
                'productivity',
                'learning-to-code',
                'soft-skills',
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
             * Ensure that the values are definitely lowercase for fields like 'post_type', and 'status'.
             */
            $convertToLower = in_array($field, ['post_type', 'status']);
            if ($convertToLower) {
                $values = array_map('strtolower', $values);
            }

            foreach ($values as $value) {

                $formatValueByType = in_array($field, ['language', 'technology', 'tag']);
                if ($formatValueByType) {
                    $value = $this->formatValueByType($field, $value);
                }

                PostAllowedValue::firstOrCreate([
                    'name' => $value,
                    'type' => $field,
                ]);
            }
            $this->command->info("Allowed values for field '{$field}' have been seeded.");
        }
    }
}
