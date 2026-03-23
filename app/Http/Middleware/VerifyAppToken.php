<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAppToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.token');

        if (! $token) {
            abort(403, 'APP_TOKEN is not configured.');
        }

        if ($request->session()->get('app_token_verified')) {
            return $next($request);
        }

        $provided = $request->query('token') ?? $request->bearerToken();

        if (! $provided || ! hash_equals($token, $provided)) {
            abort(403, 'Invalid or missing token.');
        }

        $request->session()->put('app_token_verified', true);

        return $next($request);
    }
}
