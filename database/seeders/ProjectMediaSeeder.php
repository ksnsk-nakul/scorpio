<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectMediaSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminId    = User::where('email', $adminEmail)->value('id');

        $map = [
            'rbac-management-system' => [
                ['path' => 'images/projects/rbac-1.svg', 'alt_text' => 'RBAC shield hero'],
                ['path' => 'images/projects/rbac-2.svg', 'alt_text' => 'Role hierarchy diagram'],
                ['path' => 'images/projects/rbac-3.svg', 'alt_text' => 'User management table'],
            ],
            'epub-reader-dashboard' => [
                ['path' => 'images/projects/epub-1.svg', 'alt_text' => 'Digital bookshelf'],
                ['path' => 'images/projects/epub-2.svg', 'alt_text' => 'Reader UI with progress'],
                ['path' => 'images/projects/epub-3.svg', 'alt_text' => 'Upload and library stats'],
            ],
            'task-management-kanban' => [
                ['path' => 'images/projects/kanban-1.svg', 'alt_text' => 'Kanban board columns'],
                ['path' => 'images/projects/kanban-2.svg', 'alt_text' => 'Sprint burndown chart'],
                ['path' => 'images/projects/kanban-3.svg', 'alt_text' => 'Task detail panel'],
            ],
            'flowhaven-saas' => [
                ['path' => 'images/projects/flowhaven-1.svg', 'alt_text' => 'SaaS admin dashboard'],
                ['path' => 'images/projects/flowhaven-2.svg', 'alt_text' => 'Multi-tier pricing page'],
                ['path' => 'images/projects/flowhaven-3.svg', 'alt_text' => 'REST API documentation'],
            ],
        ];

        foreach ($map as $slug => $images) {
            $project = Project::where('slug', $slug)->first();
            if (! $project) {
                continue;
            }

            if ($project->media()->where('disk', 'static')->count() > 0) {
                continue;
            }

            foreach ($images as $img) {
                $project->media()->create([
                    'user_id'   => $adminId,
                    'disk'      => 'static',
                    'path'      => $img['path'],
                    'filename'  => basename($img['path']),
                    'mime_type' => 'image/svg+xml',
                    'size'      => 0,
                    'alt_text'  => $img['alt_text'],
                ]);
            }
        }
    }
}
