<?php

namespace App\Http\Middleware;

use Closure;

class EnforceJson
{
    /**
     * Enforce json
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
