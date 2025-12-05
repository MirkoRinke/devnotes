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
                'Frontend Development',
                'Backend Development',
                'Fullstack Development',
                'Mobile Development',
                'Game Development',
                'DevOps und Cloud',
                'Database und Storage',
                'Testing und QA',
                'Monitoring und Observability',
                'Tooling und Build Systems',
                'Data Science und AI',
                'Cybersecurity',
                'UI UX Design',
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
                // --- Web Frontend Development ---
                // Languages / Stylesheets / Preprocessors
                'HTML', // HTML is a markup language (Structure)
                'CSS',  // CSS is a stylesheet language (Presentation)
                'SCSS', // SCSS is a syntax of SASS, a CSS preprocessor
                'JavaScript', // Core language for interactivity
                'TypeScript', // JavaScript superset adding static types

                // Frameworks / Libraries (Frontend)
                'React', // JavaScript library for building user interfaces
                'Vue.js', // Progressive JavaScript framework
                'Svelte', // Radical new approach to building user interfaces
                'Angular', // Platform for building mobile and desktop web applications

                // --- Web Backend / Server-Side Development ---
                // Runtimes / Languages
                'Node.js', // Node.js is a JavaScript runtime (Server-Side JS)
                'Python', // High-level, interpreted programming language (Backend often)
                'PHP', // Popular general-purpose scripting language for web development
                'Java', // High-level, class-based, object-oriented programming language
                'Go', // Statically typed, compiled programming language (often for high-performance servers)
                'C#', // Modern, object-oriented programming language by Microsoft (.NET)
                'Ruby', // Dynamic, open source programming language
                'Kotlin', // Modern, statically typed programming language that runs on the JVM 
                'Perl', // High-level, general-purpose, interpreted programming language

                // Frameworks (Backend / Full-Stack)
                'Next.js', // React framework for production (Full-Stack)
                'Nuxt', // Framework for creating Vue.js applications (Full-Stack)
                'Remix', // Full stack web framework
                'Gatsby', // React-based framework for creating static sites/apps
                'Express', // Minimal and flexible Node.js web application framework
                'NestJS', // Progressive Node.js framework for scalable server-side applications
                'Django', // High-level Python web framework
                'Flask', // Lightweight WSGI web application framework in Python
                'Laravel', // PHP web application framework
                'Symfony', // PHP framework for web applications
                'Ruby on Rails', // Server-side web application framework written in Ruby
                'Spring', // Comprehensive framework for enterprise Java development
                'Spring Boot', // Extension of the Spring framework

                // --- System / Low-Level / Databases ---
                // Languages
                'C', // General-purpose, procedural programming language
                'C++', // Extension of the C programming language
                'Rust', // Systems programming language focused on safety and performance
                'Shell', // Shell scripting languages (Automation/OS interaction)
                'SQL', // Domain-specific language for managing relational databases

                // --- Native / Cross-Platform / App Development ---
                'Swift', // Programming language for Apple's ecosystem (macOS, iOS, etc.)
                'Dart', // Client-optimized programming language for multiple platforms (often with Flutter)
                'Flutter', // Open-source UI software development toolkit (Cross-Platform)
                'React Native', // Framework for building native apps using React (Cross-Platform)

                // --- Game Development ---
                'Unity', // Cross-platform game engine
                'Unreal Engine', // Game engine developed by Epic Games
                'Godot', // Open-source game engine
                'Lua', // Lightweight, high-level, multi-paradigm programming language (often used for scripting in games)
            ],
            'technology' => [
                // --- Design & Collaboration ---
                // Tools for UI/UX design and team communication
                'Figma', // UI/UX design and prototyping tool
                'Jira', // Project management and issue tracking
                'Trello', // Collaboration tool using boards, lists, and cards
                'Asana', // Project management platform
                'ClickUp', // All-in-one productivity platform
                'Notion', // Workspace for notes, docs, and project management
                'Slack', // Team messaging and collaboration hub

                // --- Development & Local Tools (Building / Bundling / Testing) ---
                // Bundlers / Transpilers / Dev Servers
                'Vite', // Next-generation frontend tooling
                'Webpack', // Module bundler for JavaScript applications
                'ESBuild', // Extremely fast JavaScript bundler and minifier
                'Rollup', // JavaScript module bundler for libraries

                // API & Debugging Tools
                'Postman', // API platform for building and using APIs

                // Testing Tools
                'Jest', // JavaScript testing framework
                'Mocha', // JavaScript test framework for Node.js
                'Jasmine', // Behavior-driven development framework for testing JavaScript code
                'Vitest', // Fast unit test framework powered by Vite
                'Cypress', // End-to-end testing framework for web applications
                'Playwright', // End-to-end testing framework for web apps
                'Selenium', // Browser automation tool for web app testing
                'PHPUnit', // Testing framework for PHP
                'PyTest', // Testing framework for Python
                'Unittest', // Built-in Python testing framework
                'RSpec', // Testing tool for Ruby
                'JUnit', // Unit testing framework for Java

                // --- Version Control & CI/CD ---
                'Git', // Distributed version control system
                'GitHub', // Web-based hosting for Git repositories
                'GitLab', // Complete DevOps platform
                'Jenkins', // Open-source automation server for CI/CD
                'GitHub Actions', // Widely used CI/CD and automation platform integrated into GitHub

                // --- DevOps & Infrastructure (Deployment / Provisioning) ---
                // Containerization & Orchestration
                'Docker', // Platform for developing, shipping, and running applications in containers
                'Podman', // Daemonless container engine
                'Kubernetes', // Container orchestration system

                // --- Hosting & Infrastructure Providers ---
                // Cloud Service Providers (CSPs)
                'AWS', // Amazon Web Services
                'Azure', // Microsoft Azure
                'Google Cloud', // Google Cloud Platform

                // Specialized / Focused Providers
                'Firebase', // Google's platform for app development (Backend as a Service)
                'Vercel', // Platform for frontend frameworks and static sites
                'Netlify', // Platform for building, deploying, and scaling modern web projects
                'Cloudflare', // Global cloud service for web performance and security

                // Web Servers
                'Nginx', // High-performance web server and reverse proxy
                'Apache', // Widely used HTTP web server

                // --- Data Storage & Databases ---
                // Relational (SQL)
                'MySQL', // Open-source relational database
                'PostgreSQL', // Object-relational database system
                'SQLite', // Serverless, file-based relational database

                // Non-Relational (NoSQL)
                'MongoDB', // Document database (NoSQL)
                'Redis', // In-memory data structure store (used as database, cache, and message broker)

                // --- Monitoring & Observability ---
                'Prometheus', // Open-source monitoring and alerting toolkit
                'Grafana', // Analytics and interactive visualization web application
                'Sentry', // Real-time crash reporting and error monitoring
            ],
            'tag' => [
                // General Concepts & Architecture
                'best-practices',
                'architecture',
                'performance',
                'security',
                'authentication',
                'state-management',
                'monorepo',
                'refactoring',
                'clean-code',
                'design-patterns',
                'serverless',
                'microservices',
                'design-system',

                // Specific Use Cases & Processes                
                'local-development',
                'deployment',
                'testing',
                'e2e-testing',
                'unit-testing',
                'cicd',
                'observability',
                'logging',
                'monitoring',
                'career',
                'interview-prep',

                // Very Specific Tools / Libraries (that might not fit under 'technologies')                
                'react-hooks',
                'nextjs-v14',
                'vue-3',
                'django-orm',
                'kubernetes-ingress',
                'mysql-performance',
                'vite-plugin',
                'aws-lambda',
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
