<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => $request->user() ? [
                'user'  => $request->user()->only('id', 'name', 'email', 'avatar', 'username', 'plan'),
                'roles' => $request->user()->getRoleNames(),
                'plan'  => [
                    'current'  => $request->user()->currentPlan(),
                    'features' => [
                        'github_sync'         => $request->user()->planFeature('github_sync'),
                        'analytics'           => $request->user()->planFeature('analytics'),
                        'seo_control'         => $request->user()->planFeature('seo_control'),
                        'password_pages'      => $request->user()->planFeature('password_pages'),
                        'scheduled_publish'   => $request->user()->planFeature('scheduled_publish'),
                        'contact_attachments' => $request->user()->planFeature('contact_attachments'),
                        'white_label'         => $request->user()->planFeature('white_label'),
                        'api_access'          => $request->user()->planFeature('api_access'),
                        'priority_support'    => $request->user()->planFeature('priority_support'),
                    ],
                    'limits' => [
                        'pages'         => $request->user()->planLimit('pages'),
                        'workspaces'    => $request->user()->planLimit('workspaces'),
                        'projects'      => $request->user()->planLimit('projects'),
                        'skills'        => $request->user()->planLimit('skills'),
                        'service_cards' => $request->user()->planLimit('service_cards'),
                    ],
                ],
            ] : null,
            'flash' => [
                'status'             => fn () => $request->session()->get('status'),
                'success'            => fn () => $request->session()->get('success'),
                'error'              => fn () => $request->session()->get('error'),
                'profile_success'    => fn () => $request->session()->get('profile_success'),
                'password_success'   => fn () => $request->session()->get('password_success'),
                'webhook_project_id' => fn () => $request->session()->get('webhook_project_id'),
                'webhook_url'        => fn () => $request->session()->get('webhook_url'),
                'webhook_secret'     => fn () => $request->session()->get('webhook_secret'),
            ],
            'announcements'  => fn () => Cache::remember('announcements.active', 60, fn () => Announcement::active()->get(['id', 'title', 'body', 'type', 'display', 'cta_label', 'cta_url'])),
            'wallet_balance' => fn () => $request->user()?->wallet_balance_paise ?? 0,
            'demo' => env('DEMO_MODE', false) ? [
                'enabled'  => true,
                'email'    => filled(env('DEMO_EMAIL')) ? env('DEMO_EMAIL') : env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => filled(env('DEMO_PASSWORD')) ? env('DEMO_PASSWORD') : env('ADMIN_PASSWORD', 'password'),
            ] : null,
            'layoutTemplates' => [
                'admin'  => 'stripe',
                'public' => fn () => Cache::remember('settings.layout_template_public', 60, fn () => Setting::get('layout_template_public', 'minimalist')),
            ],
        ];
    }
}
