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
        $adminId    = \App\Models\User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, "Admin user not found for [{$adminEmail}] — run UserSeeder first.");

        $page = Page::create([
            'name'         => 'Home',
            'slug'         => 'home',
            'user_id'      => $adminId,
            'is_home'      => true,
            'template'     => 'hero_cards',
            'status'       => 'published',
            'published_at' => now(),
            'blocks'       => [
                [
                    'type'  => 'hero',
                    'order' => 0,
                    'data'  => [
                        'heading'    => 'Hi, I\'m Nakul — Full-Stack Laravel Developer',
                        'subheading' => 'I build, debug, and deploy Laravel + Vue apps for startups and businesses. 2 years of production experience shipping real systems under real traffic.',
                    ],
                ],
                [
                    'type'  => 'text_image',
                    'order' => 1,
                    'data'  => [
                        'text'  => "I'm a Full-Stack Developer based in Coimbatore, Tamil Nadu, specialising in Laravel and Vue.js.\n\nI've spent 2 years in production environments — building taxi and delivery platforms, integrating payment gateways, and keeping live systems running under real traffic. I know what it takes to ship fast and fix faster.\n\nWhether you need a new Laravel app built from scratch, an existing one debugged and optimised, or a REST API wired up for your mobile or web client — I can help.",
                        'image' => '',
                    ],
                ],
                [
                    'type'  => 'service_cards',
                    'order' => 2,
                    'data'  => [
                        'heading' => 'What I Can Do For You',
                    ],
                ],
                [
                    'type'  => 'project_grid',
                    'order' => 3,
                    'data'  => [
                        'heading'  => 'Projects',
                        'projects' => [
                            [
                                'title'       => 'Scorpio CMS',
                                'description' => 'Self-hosted portfolio CMS built on Laravel 13 + Inertia.js + Vue 3. Block-based page editor, GitHub issue sync, 4-method auth (Google, GitHub, OTP, password), RBAC, media library, and Railway deployment.',
                                'url'         => 'https://github.com/ksnsk2001-boop',
                            ],
                            [
                                'title'       => 'Scorpio Installer',
                                'description' => 'GUI installer wizard for Scorpio CMS — guided setup for environment config, database migration, and admin seeding without touching the terminal.',
                                'url'         => '#',
                            ],
                            [
                                'title'       => 'MERN Migration',
                                'description' => 'Translating the Scorpio backend from Laravel to a Node.js + Express + MongoDB stack while preserving the same API contract and Vue 3 frontend.',
                                'url'         => '#',
                            ],
                            [
                                'title'       => 'Vue Template Conversion',
                                'description' => 'Converting static HTML/CSS templates into reusable Vue 3 components with Inertia.js integration — faster onboarding for client projects.',
                                'url'         => '#',
                            ],
                        ],
                    ],
                ],
                [
                    'type'  => 'contact_form',
                    'order' => 4,
                    'data'  => [
                        'heading' => 'Let\'s work together',
                        'email'   => 'ksnsk2001@gmail.com',
                        'phone'   => '+91 63823 51281',
                        'links'   => [
                            ['label' => 'GitHub',   'url' => 'https://github.com/ksnsk2001-boop'],
                            ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/nakul-sri-kuber-384233193/'],
                        ],
                    ],
                ],
            ],
        ]);

        $services = [
            ['icon' => '⚙️',  'title' => 'Laravel App Development',      'description' => 'Build, manage, and debug Laravel 10+ apps with Blade or Vue 3. Clean MVC architecture, Eloquent ORM, and Artisan-first workflows.',                                             'sort_order' => 1],
            ['icon' => '💳',  'title' => 'Payment Gateway Integration',   'description' => 'Stripe, Razorpay, Paymongo, PayPal, MyFatoorah — secure web checkout flows and webhook handling done right.',                                                                       'sort_order' => 2],
            ['icon' => '🔌',  'title' => 'Mobile & Web API',              'description' => 'RESTful API development and support for Laravel backends serving mobile apps or third-party web clients.',                                                                           'sort_order' => 3],
            ['icon' => '🗂️', 'title' => 'Portfolio & Resume Platform',   'description' => 'Deploy and customise Scorpio CMS — a self-hosted portfolio platform built for developers. GitHub sync, block editor, multi-auth included.',                                          'sort_order' => 4],
            ['icon' => '🐛',  'title' => 'Production Debug & Support',    'description' => "Live system diagnostics, query optimisation, hotfixes, and long-term stability improvements. I've fixed production under pressure.",                                                 'sort_order' => 5],
            ['icon' => '🚀',  'title' => 'Deployment & DevOps',           'description' => "AWS EC2, GCS VM, Apache, SSL/TLS with Certbot, domain and subdomain routing — I handle the server so you don't have to.",                                                           'sort_order' => 6],
        ];

        foreach ($services as $service) {
            ServiceCard::create(array_merge($service, [
                'page_id'  => $page->id,
                'user_id'  => $page->user_id,
                'featured' => true,
            ]));
        }
    }
}
