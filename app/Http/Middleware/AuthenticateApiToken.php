<?php

namespace App\Http\Middleware;

use App\Models\UserToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token autentikasi tidak ditemukan. Silakan login kembali.',
            ], 401);
        }

        $hashedToken = hash('sha256', $bearerToken);

        $tokenRecord = UserToken::with('user')
            ->where('token', $hashedToken)
            ->first();

        if (! $tokenRecord || ! $tokenRecord->user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sesi telah berakhir.',
            ], 401);
        }

        if ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast()) {
            $tokenRecord->delete();

            return response()->json([
                'success' => false,
                'message' => 'Token sudah kedaluwarsa. Silakan login kembali.',
            ], 401);
        }

        $tokenRecord->forceFill(['last_used_at' => now()])->save();

        $user = $tokenRecord->user;
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('current_api_token', $tokenRecord);
        Auth::setUser($user);

        return $next($request);
    }
}
