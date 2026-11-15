<?php

namespace App\Http\Middleware;

use App\Services\Visitors;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages: log out blocked users, and record daily active visitors (once per visitor per day).
 */
class TrackActivity
{
    public function __construct(private Visitors $visitors) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(lroute('auth.login'))->withErrors(['email' => __('This account has been blocked.')]);
        }

        if ($request->isMethod('GET')) {
            $this->visitors->recordActive($request);
        }

        return $next($request);
    }
}
