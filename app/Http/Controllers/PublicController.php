<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
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

        // User exists but hasn't published a home page yet — show a stub
        // instead of a hard 404, since the username itself is valid.
        if (! $page) {
            return Inertia::render('Public/ProfileStub', [
                'owner' => $user->only('name', 'username'),
            ]);
        }

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('name', 'username'),
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
            'owner'      => $user->only('name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,description,github_repo,status')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
        ]);
    }

    /**
     * Per-tenant branding falls back to the platform-wide site_name when a
     * user hasn't set their own; og_image has no global fallback since it's
     * inherently tied to one tenant's content.
     */
    private function tenantSettings(\App\Models\User $user): array
    {
        return [
            'site_name' => $user->site_name ?? Setting::get('site_name'),
            'og_image'  => $user->og_image,
        ];
    }

    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        // Root domain isn't tied to a single tenant — route to the first
        // user with a published home page rather than mixing every
        // tenant's blocks into one feed (which would leak cross-tenant content).
        $homeUser = \App\Models\User::whereHas('pages', fn ($q) => $q->where('is_home', true)->where('status', 'published'))
            ->orderBy('id')
            ->first(['id', 'username']);

        if ($homeUser) {
            return redirect("/{$homeUser->username}");
        }

        $settings = Setting::whereIn('key', ['site_name', 'site_tagline', 'meta_description'])
            ->pluck('value', 'key');

        return Inertia::render('Public/Home', [
            'pages'    => [],
            'settings' => $settings,
        ]);
    }
}
