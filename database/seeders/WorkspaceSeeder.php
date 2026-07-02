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

        $workspace = Workspace::create([
            'name'    => 'My Projects',
            'slug'    => 'my-projects',
            'user_id' => $adminId,
        ]);

        $projects = [
            [
                'name'        => 'RBAC Management System',
                'slug'        => 'rbac-management-system',
                'description' => 'A role-based access control system for managing users, roles, and permissions in web applications. Designed to demonstrate secure authorization flows and scalable backend architecture.',
                'tags'        => ['Laravel', 'MySQL', 'Authentication', 'Authorization'],
                'github_repo' => 'ksnsk-nakul/rbac-management',
                'demo_url'    => null,
                'status'      => 'active',
                'sort_order'  => 0,
            ],
            [
                'name'        => 'EPUB Reader Dashboard',
                'slug'        => 'epub-reader-dashboard',
                'description' => 'A web-based EPUB reader and management dashboard that allows users to upload, organize, and read ebooks online. Built as the foundation for a future ebook marketplace platform.',
                'tags'        => ['Laravel', 'Vue.js', 'MySQL', 'File Storage'],
                'github_repo' => 'ksnsk-nakul/epub-reader',
                'demo_url'    => null,
                'status'      => 'active',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Task Management (Kanban) App',
                'slug'        => 'task-management-kanban',
                'description' => 'A Kanban-style task management application for organizing workflows, tracking progress, and managing project tasks. Supports structured task stages and collaborative project flow.',
                'tags'        => ['Laravel', 'Vue.js', 'REST API', 'MySQL'],
                'github_repo' => 'ksnsk-nakul/kanban-app',
                'demo_url'    => null,
                'status'      => 'active',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'FlowHaven SaaS Platform',
                'slug'        => 'flowhaven-saas',
                'description' => 'A decoupled SaaS platform built with an API-first architecture. The backend provides Laravel REST APIs while the frontend uses Vue, Pinia, and TypeScript to build a scalable single-page application.',
                'tags'        => ['Laravel API', 'Vue.js', 'Pinia', 'TypeScript'],
                'github_repo' => 'ksnsk-nakul/flowhaven',
                'demo_url'    => null,
                'status'      => 'active',
                'sort_order'  => 3,
            ],
        ];

        foreach ($projects as $project) {
            Project::create(array_merge($project, ['workspace_id' => $workspace->id]));
        }
    }
}
