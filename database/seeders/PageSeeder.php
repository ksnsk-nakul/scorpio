<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\ServiceCard;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        if (Page::exists()) {
            return;
        }

        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminName  = filled(env('ADMIN_NAME'))  ? env('ADMIN_NAME')  : 'Your Name';
        $adminId    = \App\Models\User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, "Admin user not found for [{$adminEmail}] — run UserSeeder first.");

        $page = Page::create([
            'name'     => 'Home',
            'slug'     => 'home',
            'user_id'  => $adminId,
            'is_home'  => true,
            'template' => 'hero_cards',
            'status'   => 'published',
            'blocks'   => [
                [
                    'type'  => 'hero',
                    'order' => 0,
                    'data'  => [
                        'heading'       => $adminName,
                        'subheading'    => 'Full Stack Developer | Laravel | Vue | API Development',
                        'rotating_text' => [
                            'Deploying reliable software.',
                            'Building scalable APIs.',
                            'Crafting elegant UIs.',
                        ],
                    ],
                ],
                [
                    'type'  => 'about',
                    'order' => 1,
                    'data'  => [
                        'bio' => "I'm a full stack developer specializing in Laravel and Vue.js. I focus on clean architecture, maintainable codebases, and API-first development that scales.\n\nMy workflow blends backend reliability with frontend performance, ensuring the product remains fast and secure from MVP to production.",
                        'overview' => [
                            'Backend systems with Laravel and RESTful APIs',
                            'Responsive Vue-based UIs and component-driven design',
                            'DevOps workflows with Docker, Linux, and cloud platforms',
                        ],
                    ],
                ],
                [
                    'type'  => 'skills',
                    'order' => 2,
                    'data'  => [
                        'heading' => 'Skills',
                        'skills'  => [
                            ['name' => 'Laravel',    'icon' => '🧱'],
                            ['name' => 'PHP',        'icon' => '🐘'],
                            ['name' => 'Vue.js',     'icon' => '💚'],
                            ['name' => 'JavaScript', 'icon' => '🟨'],
                            ['name' => 'HTML5',      'icon' => '🌐'],
                            ['name' => 'CSS3',       'icon' => '🎨'],
                            ['name' => 'MySQL',      'icon' => '🗄️'],
                            ['name' => 'REST API',   'icon' => '🔌'],
                            ['name' => 'Linux',      'icon' => '🐧'],
                            ['name' => 'Docker',     'icon' => '🐳'],
                            ['name' => 'AWS',        'icon' => '☁️'],
                            ['name' => 'Git',        'icon' => '🔀'],
                        ],
                    ],
                ],
                [
                    'type'  => 'project_grid',
                    'order' => 3,
                    'data'  => [
                        'heading'      => 'Projects',
                        'workspace_id' => null,
                        'projects'     => [
                            [
                                'title'       => 'RBAC Management System',
                                'description' => 'A role-based access control system for managing users, roles, and permissions in web applications. Designed to demonstrate secure authorization flows and scalable backend architecture.',
                                'tech'        => 'Laravel, MySQL, Authentication, Authorization',
                                'github'      => null,
                                'url'         => '#',
                            ],
                            [
                                'title'       => 'EPUB Reader Dashboard',
                                'description' => 'A web-based EPUB reader and management dashboard that allows users to upload, organize, and read ebooks online. Built as the foundation for a future ebook marketplace platform.',
                                'tech'        => 'Laravel, Vue.js, MySQL, File Storage',
                                'github'      => null,
                                'url'         => '#',
                            ],
                            [
                                'title'       => 'Task Management (Kanban) App',
                                'description' => 'A Kanban-style task management application for organizing workflows, tracking progress, and managing project tasks. Supports structured task stages and collaborative project flow.',
                                'tech'        => 'Laravel, Vue.js, REST API, MySQL',
                                'github'      => null,
                                'url'         => '#',
                            ],
                            [
                                'title'       => 'FlowHaven SaaS Platform',
                                'description' => 'A decoupled SaaS platform built with an API-first architecture. The backend provides Laravel REST APIs while the frontend uses Vue, Pinia, and TypeScript to build a scalable single-page application.',
                                'tech'        => 'Laravel API, Vue.js, Pinia, TypeScript',
                                'github'      => null,
                                'url'         => '#',
                            ],
                        ],
                    ],
                ],
                [
                    'type'  => 'experience',
                    'order' => 4,
                    'data'  => [
                        'heading' => 'Experience',
                        'items'   => [
                            [
                                'period'      => '2026 - Present',
                                'title'       => 'Full Stack Developer (Freelance)',
                                'company'     => '',
                                'description' => 'Leading API architecture and frontend delivery for SaaS products.',
                            ],
                            [
                                'period'      => '2023 - 2026',
                                'title'       => 'Full Stack Developer',
                                'company'     => '',
                                'description' => 'Leading API architecture, application deploy, SMS, payment gateway integrations and frontend delivery for web applications.',
                            ],
                            [
                                'period'      => '2021 - 2023',
                                'title'       => 'Full Stack Developer Intern',
                                'company'     => '',
                                'description' => 'Learnt Basic HTML, CSS, Bootstrap, Javascript, PHP and MySQL with a year gap.',
                            ],
                        ],
                    ],
                ],
                [
                    'type'  => 'contact_form',
                    'order' => 5,
                    'data'  => [
                        'heading' => 'Contact',
                        'email'   => $adminEmail,
                        'phone'   => '',
                        'links'   => [
                            ['label' => 'github.com/ksnsk-nakul',                  'url' => 'https://github.com/ksnsk-nakul',                          'icon' => '🐙'],
                            ['label' => 'linkedin.com/in/nakul-sri-kuber-384233193', 'url' => 'https://linkedin.com/in/nakul-sri-kuber-384233193',        'icon' => '💼'],
                        ],
                    ],
                ],
            ],
        ]);

        $services = [
            ['icon' => '💻', 'title' => 'Web Development',    'description' => 'Building fast, reliable web applications using modern frameworks and best practices.'],
            ['icon' => '🎨', 'title' => 'UI / UX Design',      'description' => 'Designing clean, intuitive interfaces that users love to interact with.'],
            ['icon' => '🚀', 'title' => 'Deployment & DevOps', 'description' => 'Setting up CI/CD pipelines, cloud infrastructure, and production deployments.'],
        ];

        foreach ($services as $i => $service) {
            ServiceCard::create([
                'user_id'     => $adminId,
                'page_id'     => $page->id,
                'icon'        => $service['icon'],
                'title'       => $service['title'],
                'description' => $service['description'],
                'sort_order'  => $i,
                'featured'    => true,
                'tags'        => [],
            ]);
        }
    }
}
