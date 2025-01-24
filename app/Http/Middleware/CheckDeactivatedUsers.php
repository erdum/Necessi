<?php

namespace App\Http\Middleware;

use App\Exceptions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeactivatedUsers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()?->deactivated) {
            throw new Exceptions\BaseException(
                'Your account is deactivated, contact support.',
                403
            );
        }

        return $next($request);
    }
}
