<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log out an account the moment an admin disables it.
 *
 * Login already refuses disabled accounts, but someone who was signed in (or
 * holds a "remember me" cookie) before being disabled would otherwise keep
 * access until their session ran out.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(401, 'Your account has been disabled.');
            }

            return redirect()->route('login')
                ->withErrors(['username' => __('Your account has been disabled. Please contact the administrator.')]);
        }

        return $next($request);
    }
}
