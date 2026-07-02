<?php

namespace Database\Seeders;

use App\Models\AboutSection;
use App\Models\ExperienceItem;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminId    = User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, "Admin user not found — run UserSeeder first.");

        // ── Skills (skip if already seeded) ──────────────────────────────────
        if (! Skill::where('user_id', $adminId)->exists()) {
            $skills = [
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
            ];
            foreach ($skills as $i => $s) {
                Skill::create(array_merge($s, ['user_id' => $adminId, 'sort_order' => $i, 'is_seeded' => true]));
            }
        }

        // ── About (skip if already seeded) ───────────────────────────────────
        if (! AboutSection::where('user_id', $adminId)->exists()) {
            AboutSection::create([
                'user_id'   => $adminId,
                'bio'       => "I'm a full stack developer specializing in Laravel and Vue.js. I focus on clean architecture, maintainable codebases, and API-first development that scales.\n\nMy workflow blends backend reliability with frontend performance, ensuring the product remains fast and secure from MVP to production.",
                'overview'  => [
                    'Backend systems with Laravel and RESTful APIs',
                    'Responsive Vue-based UIs and component-driven design',
                    'DevOps workflows with Docker, Linux, and cloud platforms',
                ],
                'is_seeded' => true,
            ]);
        }

        // ── Experience (skip if already seeded) ──────────────────────────────
        if (! ExperienceItem::where('user_id', $adminId)->exists()) {
            $items = [
                ['period' => '2026 - Present', 'title' => 'Full Stack Developer (Freelance)', 'company' => '', 'description' => 'Leading API architecture and frontend delivery for SaaS products.'],
                ['period' => '2023 - 2026',    'title' => 'Full Stack Developer',             'company' => '', 'description' => 'API architecture, application deploy, SMS, payment gateway integrations and frontend delivery for web applications.'],
                ['period' => '2021 - 2023',    'title' => 'Full Stack Developer Intern',       'company' => '', 'description' => 'Learnt Basic HTML, CSS, Bootstrap, Javascript, PHP and MySQL with a year gap.'],
            ];
            foreach ($items as $i => $item) {
                ExperienceItem::create(array_merge($item, ['user_id' => $adminId, 'sort_order' => $i, 'is_seeded' => true]));
            }
        }
    }
}
