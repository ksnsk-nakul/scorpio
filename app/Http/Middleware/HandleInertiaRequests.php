<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
                'user'  => $request->user()->only('id', 'name', 'email', 'avatar', 'username'),
                'roles' => $request->user()->getRoleNames(),
            ] : null,
            'flash' => [
                'status'             => fn () => $request->session()->get('status'),
                'success'            => fn () => $request->session()->get('success'),
                'profile_success'    => fn () => $request->session()->get('profile_success'),
                'password_success'   => fn () => $request->session()->get('password_success'),
                'webhook_project_id' => fn () => $request->session()->get('webhook_project_id'),
                'webhook_url'        => fn () => $request->session()->get('webhook_url'),
                'webhook_secret'     => fn () => $request->session()->get('webhook_secret'),
            ],
            'demo' => env('DEMO_MODE', false) ? [
                'enabled'  => true,
                'email'    => filled(env('DEMO_EMAIL')) ? env('DEMO_EMAIL') : env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => filled(env('DEMO_PASSWORD')) ? env('DEMO_PASSWORD') : env('ADMIN_PASSWORD', 'password'),
            ] : null,
        ];
    }
}
