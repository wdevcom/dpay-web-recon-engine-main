<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** API zawsze odpowiada JSON-em, także przy błędach walidacji i 404. */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
