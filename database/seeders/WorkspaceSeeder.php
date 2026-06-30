<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        if (Workspace::exists()) {
            return;
        }

        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminId    = User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, "Admin user not found for [{$adminEmail}] — run UserSeeder first.");

        $ws = Workspace::create([
            'name'        => 'Personal Projects',
            'slug'        => 'personal-projects',
            'description' => 'My open-source and client work.',
            'user_id'     => $adminId,
        ]);

        $projects = [
            [
                'name'        => 'Scorpio CMS',
                'slug'        => 'scorpio-cms',
                'description' => 'Self-hosted portfolio CMS built on Laravel 13 + Inertia.js + Vue 3. Block-based page editor, GitHub issue sync, 4-method auth, RBAC, and Railway deployment.',
                'github_repo' => 'ksnsk2001-boop/scorpio',
                'status'      => 'active',
            ],
            [
                'name'        => 'Scorpio Installer',
                'slug'        => 'scorpio-installer',
                'description' => 'GUI installer wizard for Scorpio CMS — guided setup for environment config, database migration, and admin seeding without touching the terminal.',
                'github_repo' => null,
                'status'      => 'active',
            ],
            [
                'name'        => 'MERN Migration',
                'slug'        => 'mern-migration',
                'description' => 'Translating the Scorpio backend from Laravel to a Node.js + Express + MongoDB stack while preserving the same API contract and Vue 3 frontend.',
                'github_repo' => null,
                'status'      => 'active',
            ],
            [
                'name'        => 'Vue Template Conversion',
                'slug'        => 'vue-template-conversion',
                'description' => 'Converting static HTML/CSS templates into reusable Vue 3 components with Inertia.js integration — faster onboarding for client projects.',
                'github_repo' => null,
                'status'      => 'active',
            ],
        ];

        foreach ($projects as $project) {
            Project::create(array_merge($project, ['workspace_id' => $ws->id]));
        }
    }
}
