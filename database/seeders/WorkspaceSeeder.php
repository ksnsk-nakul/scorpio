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

        $products = [
            [
                'name'        => 'Portfolio CMS',
                'slug'        => 'portfolio-cms',
                'description' => 'A multi-tenant portfolio and CMS platform built with Laravel, Inertia, and Vue.',
                'status'      => 'active',
                'sort_order'  => 0,
            ],
            [
                'name'        => 'Open Source Tool',
                'slug'        => 'open-source-tool',
                'description' => 'A developer utility released as open source. Replace with your own project.',
                'status'      => 'active',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Client Project',
                'slug'        => 'client-project',
                'description' => 'A web application delivered for a client. Update this with your own work.',
                'status'      => 'active',
                'sort_order'  => 2,
            ],
        ];

        foreach ($products as $product) {
            Project::create(array_merge($product, ['workspace_id' => $workspace->id]));
        }
    }
}
