<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function portfolio(string $username): Response
    {
        // select() prevents loading sensitive columns (password, github_token, etc.)
        $user = \App\Models\User::where('username', $username)
            ->select(['id', 'name', 'username'])
            ->firstOrFail();

        // status=published check intentionally returns 404 (not 403) to avoid disclosing draft existence
        $page = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'     => $page,
            'owner'    => $user->only('name', 'username'),
            'settings' => Setting::whereIn('key', ['site_name', 'og_image'])->pluck('value', 'key'),
        ]);
    }

    public function portfolioPage(string $username, string $slug): Response
    {
        $user = \App\Models\User::where('username', $username)
            ->select(['id', 'name', 'username'])
            ->firstOrFail();

        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'     => $page,
            'owner'    => $user->only('name', 'username'),
            'settings' => Setting::whereIn('key', ['site_name', 'og_image'])->pluck('value', 'key'),
        ]);
    }

    public function index(): Response
    {
        $pages = Page::where('status', 'published')
            ->orderBy('published_at')
            ->with(['serviceCards' => fn ($q) => $q->orderBy('sort_order')])
            ->get(['id', 'name', 'slug', 'template', 'blocks']);

        $settings = Setting::whereIn('key', ['site_name', 'site_tagline', 'meta_description'])
            ->pluck('value', 'key');

        return Inertia::render('Public/Home', [
            'pages'    => $pages,
            'settings' => $settings,
        ]);
    }
}
