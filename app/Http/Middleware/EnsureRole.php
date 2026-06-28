<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }
        if (! $request->user()->hasAnyRole($roles)) {
            abort(403, 'Forbidden.');
        }
        return $next($request);
    }
}
