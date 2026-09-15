<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return response()->json([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Login diperlukan.',
            ], 401);
        }

        $accessToken = AccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $accessToken || ! $accessToken->user || $accessToken->user->status !== 'ACTIVE') {
            return response()->json([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Sesi tidak valid.',
            ], 401);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return response()->json([
                'code' => 'SESSION_EXPIRED',
                'message' => 'Sesi sudah berakhir. Silakan login ulang.',
            ], 401);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($accessToken->user);
        $request->attributes->set('access_token', $accessToken);

        return $next($request);
    }
}
