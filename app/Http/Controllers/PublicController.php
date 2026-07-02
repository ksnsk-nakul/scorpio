<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function index(): Response
    {
        $user = $this->adminUser();

        if (! $user) {
            return Inertia::render('Public/Home', ['pages' => [], 'settings' => Setting::whereIn('key', ['site_name', 'site_tagline', 'meta_description'])->pluck('value', 'key')]);
        }

        $page = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with('serviceCards')
            ->first();

        if (! $page) {
            return Inertia::render('Public/ProfileStub', [
                'owner' => $user->only('id', 'name', 'username'),
            ]);
        }

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('id', 'name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,description,github_repo,status')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
        ]);
    }

    public function adminPage(string $slug): Response
    {
        $user = $this->adminUser();

        abort_if(! $user, 404);

        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('id', 'name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,description,github_repo,status')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
        ]);
    }

    public function portfolio(string $username): Response
    {
        // select() prevents loading sensitive columns (password, github_token, etc.)
        $user = \App\Models\User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->firstOrFail();

        $page = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with('serviceCards')
            ->first();

        if (! $page) {
            return Inertia::render('Public/ProfileStub', [
                'owner' => $user->only('id', 'name', 'username'),
            ]);
        }

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('id', 'name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,description,github_repo,status')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
        ]);
    }

    public function portfolioPage(string $username, string $slug): Response
    {
        $user = \App\Models\User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->firstOrFail();

        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('id', 'name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,description,github_repo,status')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
        ]);
    }

    private function adminUser(): ?\App\Models\User
    {
        return \App\Models\User::role('admin')
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->orderBy('id')
            ->first();
    }

    private function tenantSettings(\App\Models\User $user): array
    {
        return [
            'site_name'          => $user->site_name ?? Setting::get('site_name'),
            'og_image'           => $user->og_image,
            'show_donate_banner' => (bool) Setting::get('show_donate_banner', false),
        ];
    }
}
