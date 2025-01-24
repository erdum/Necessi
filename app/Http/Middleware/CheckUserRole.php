<?php

namespace App\Http\Middleware;

use App\Exceptions;
use Closure;
use Illuminate\Http\Request;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();

        if (! $user || ! $user instanceof $role) {
            throw new Exceptions\AccessForbidden;
        }

        return $next($request);
    }
}
