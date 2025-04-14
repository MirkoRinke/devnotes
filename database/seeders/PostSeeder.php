<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {

        // Example post 1
        Post::create([
            'user_id' => 1,
            'title' => "Svelte Store: Einfaches State Management",
            'code' =>  "import { writable } from 'svelte/store';",
            'description' => "Entdecken Sie die Vorteile von Svelte Stores für einfaches State Management.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://svelte.dev/docs#run-time-store", "https://svelte-tutorial.net"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://svelte.dev/docs#run-time-store",
                    "type" => "resources",
                    "domain" => "svelte.dev"
                ],
                [
                    "url" => "https://svelte-tutorial.net",
                    "type" => "resources",
                    "domain" => "svelte-tutorial.net"
                ]
            ],
            'language' => "JavaScript",
            'category' => "Frontend-Entwicklung",
            'tags' => ["svelte", "store", "state-management"],
            'status' => "published",
        ]);

        // Example post 2
        Post::create([
            'user_id' => 4,
            'title' => "Laravel 8: Eloquent ORM",
            'code' =>  "use App\Models\User;",
            'description' => "Erfahren Sie, wie Sie mit Laravel 8 das Eloquent ORM verwenden.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://laravel.com/docs/8.x/eloquent"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://laravel.com/docs/8.x/eloquent",
                    "type" => "resources",
                    "domain" => "laravel.com"
                ]
            ],
            'language' => "PHP",
            'category' => "Backend-Entwicklung",
            'tags' => ["laravel", "eloquent", "orm"],
            'status' => "published",
        ]);

        // Example post 3
        Post::create([
            'user_id' => 8,
            'title' => "Vue 3: Composition API",
            'code' =>  "import { ref } from 'vue';",
            'description' => "Erfahren Sie, wie Sie mit der Composition API in Vue 3 arbeiten.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://v3.vuejs.org/guide/composition-api-introduction.html"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://v3.vuejs.org/guide/composition-api-introduction.html",
                    "type" => "resources",
                    "domain" => "v3.vuejs.org"
                ]
            ],
            'language' => "JavaScript",
            'category' => "Frontend-Entwicklung",
            'tags' => ["vue", "composition-api"],
            'status' => "published",
        ]);

        // Example post 4
        Post::create([
            'user_id' => 9,
            'title' => "React: Functional Components",
            'code' =>  "import React from 'react';",
            'description' => "Erfahren Sie, wie Sie mit React funktionale Komponenten erstellen.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://reactjs.org/docs/components-and-props.html"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://reactjs.org/docs/components-and-props.html",
                    "type" => "resources",
                    "domain" => "reactjs.org"
                ]
            ],
            'language' => "JavaScript",
            'category' => "Frontend-Entwicklung",
            'tags' => ["react", "functional-components"],
            'status' => "draft",
        ]);

        // Example post 5
        Post::create([
            'user_id' => 6,
            'title' => "Node.js: RESTful API",
            'code' =>  "const express = require('express');",
            'description' => "Erfahren Sie, wie Sie mit Node.js eine RESTful API erstellen.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://expressjs.com/en/starter/hello-world.html"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://expressjs.com/en/starter/hello-world.html",
                    "type" => "resources",
                    "domain" => "expressjs.com"
                ]
            ],
            'language' => "JavaScript",
            'category' => "Backend-Entwicklung",
            'tags' => ["node.js", "restful-api"],
            'status' => "published",
        ]);

        // Example post 6
        Post::create([
            'user_id' => 8,
            'title' => "Docker: Containerisierung",
            'code' =>  "docker run -d -p 80:80 nginx",
            'description' => "Erfahren Sie, wie Sie mit Docker Anwendungen containerisieren.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://docs.docker.com/get-started/overview/"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://docs.docker.com/get-started/overview/",
                    "type" => "resources",
                    "domain" => "docs.docker.com"
                ]
            ],
            'language' => "Shell",
            'category' => "DevOps",
            'tags' => ["docker", "containerization"],
            'status' => "published",
        ]);

        // Example post 7
        Post::create([
            'user_id' => 1,
            'title' => "Git: Branching",
            'code' =>  "git checkout -b feature-branch",
            'description' => "Erfahren Sie, wie Sie mit Git Branches erstellen und verwalten.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell",
                    "type" => "resources",
                    "domain" => "git-scm.com"
                ]
            ],
            'language' => "Shell",
            'category' => "DevOps",
            'tags' => ["git", "branching"],
            'status' => "draft",
        ]);

        // Example post 8
        Post::create([
            'user_id' => 9,
            'title' => "Python: Data Science",
            'code' =>  "import pandas as pd",
            'description' => "Erfahren Sie, wie Sie mit Python Data Science Projekte umsetzen.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://www.python.org/doc/"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.python.org/doc/",
                    "type" => "resources",
                    "domain" => "www.python.org"
                ]
            ],
            'language' => "Python",
            'category' => "Data Science",
            'tags' => ["python", "data-science"],
            'status' => "published",
        ]);

        // Example post 9
        Post::create([
            'user_id' => 4,
            'title' => "AWS: S3 Bucket",
            'code' =>  "aws s3 ls",
            'description' => "Erfahren Sie, wie Sie mit AWS S3 Buckets Dateien speichern und verwalten.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://docs.aws.amazon.com/s3/index.html"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://docs.aws.amazon.com/s3/index.html",
                    "type" => "resources",
                    "domain" => "docs.aws.amazon.com"
                ]
            ],
            'language' => "Shell",
            'category' => "Cloud Computing",
            'tags' => ["aws", "s3", "cloud"],
            'status' => "archived",
        ]);

        // Example post 10
        Post::create([
            'user_id' => 7,
            'title' => "GraphQL: Query Language",
            'code' =>  "query { user { name } }",
            'description' => "Erfahren Sie, wie Sie mit GraphQL Daten abfragen und verwalten.",
            'images' => ["https://picsum.photos/200", "https://picsum.photos/200"],
            'videos' => ["https://www.youtube.com/watch?v=dQw4w9WgXcQ", "https://www.youtube.com/watch?v=dQw4w9WgXcQ"],
            'resources' => ["https://graphql.org/learn/"],
            'external_source_previews' => [
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://picsum.photos/200",
                    "type" => "images",
                    "domain" => "picsum.photos"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
                    "type" => "videos",
                    "domain" => "www.youtube.com"
                ],
                [
                    "url" => "https://graphql.org/learn/",
                    "type" => "resources",
                    "domain" => "graphql.org"
                ]
            ],
            'language' => "GraphQL",
            'category' => "Backend-Entwicklung",
            'tags' => ["graphql", "query-language"],
            'status' => "archived",
        ]);
    }
}
