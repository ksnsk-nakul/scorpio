<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
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
