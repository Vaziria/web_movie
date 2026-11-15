<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Admin → Users: visitor accounts. Admin accounts are listed but cannot be blocked or deleted here. */
class UserController extends Controller
{
    public const FILTERS = ['all' => 'All', 'google' => 'Google login', 'email' => 'Email only', 'blocked' => 'Blocked', 'admins' => 'Admins'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : 'all';

        $users = User::query()
            ->withCount('watches')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($filter === 'google', fn ($w) => $w->whereNotNull('google_id'))
            ->when($filter === 'email', fn ($w) => $w->whereNull('google_id'))
            ->when($filter === 'blocked', fn ($w) => $w->whereNotNull('blocked_at'))
            ->when($filter === 'admins', fn ($w) => $w->where('is_admin', true))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'q' => $q, 'filter' => $filter, 'total' => User::count()]);
    }

    public function show(User $user): View
    {
        $watched = DB::table('movie_watches')->join('movies', 'movies.id', '=', 'movie_watches.movie_id')
            ->where('movie_watches.user_id', $user->id)->orderByDesc('movie_watches.last_at')->limit(20)
            ->get(['movies.title', 'movies.slug', 'movie_watches.last_at']);

        return view('admin.users.show', [
            'user' => $user->loadCount('watches'),
            'watched' => $watched,
            'activeDays' => DB::table('daily_actives')->where('visitor', 'u:'.$user->id)->count(),
        ]);
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $this->guard($request, $user);
        $user->forceFill(['blocked_at' => now(), 'remember_token' => null])->save();
        $user->tokens()->delete();

        return back()->with('status', "Blocked {$user->email}. They are logged out on their next page view.");
    }

    public function unblock(User $user): RedirectResponse
    {
        $user->forceFill(['blocked_at' => null])->save();

        return back()->with('status', "Unblocked {$user->email}.");
    }

    public function sendReset(User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'Admins use the admin "Forgot password?" page.');
        app()->setLocale('id');   // the email links to the Indonesian page
        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', $status === Password::RESET_LINK_SENT
            ? "Reset link sent to {$user->email} (if it doesn't arrive, check their spam folder)."
            : 'Not sent: '.__($status));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guard($request, $user);
        $email = $user->email;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Deleted {$email}.");
    }

    private function guard(Request $request, User $user): void
    {
        abort_if($user->is_admin || $user->is($request->user()), 403, 'Admin accounts cannot be blocked or deleted here.');
    }
}
