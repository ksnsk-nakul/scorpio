<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the request host matches a tenant's custom_domain, transparently
 * serve their portfolio at "/" instead of requiring /{username}. Admin
 * routes, auth routes, and asset/storage paths are left untouched so the
 * underlying app (and the tenant's own login) keep working on the domain.
 */
class ResolveCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if ($request->is('admin/*', 'auth/*', 'login*', 'register', 'storage/*', 'build/*', 'up', 'webhooks/*')) {
            return $next($request);
        }

        $user = User::whereNotNull('custom_domain')
            ->where('custom_domain', $host)
            ->first(['id', 'username']);

        if (! $user) {
            return $next($request);
        }

        $path = $request->path() === '/' ? '' : '/' . ltrim($request->path(), '/');

        $rewritten = Request::create("/{$user->username}{$path}", $request->method(), $request->query->all());
        $rewritten->setLaravelSession($request->session());
        $rewritten->headers = $request->headers;

        return app()->handle($rewritten);
    }
}
