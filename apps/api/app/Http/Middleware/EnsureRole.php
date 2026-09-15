<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $roles = collect(array_slice(func_get_args(), 2))
            ->flatMap(fn (string $role) => explode(',', $role))
            ->filter()
            ->values()
            ->all();
        $user = $request->user();

        if (! $user || ($roles !== [] && ! in_array($user->role, $roles, true))) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Akses tidak diizinkan.',
            ], 403);
        }

        return $next($request);
    }
}
