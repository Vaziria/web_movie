<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Users & statistics', 'Visitor accounts and daily/monthly active numbers (read-only). Block, delete and reset links are in the admin panel.', weight: 6)]
class UserController extends Controller
{
    /**
     * List users
     *
     * Newest first, 50 per page. `watches_count` = movies the user opened.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $request->validate([
            /** Search in name and email. */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $q = trim((string) $request->query('q', ''));

        $page = User::query()->withCount('watches')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->latest('id')->paginate(50)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'google' => $u->google_id !== null,
                'is_admin' => (bool) $u->is_admin,
                'blocked' => $u->isBlocked(),
                'premium_until' => $u->premium_until?->toIso8601String(),
                'watches_count' => (int) $u->watches_count,
                'created_at' => $u->created_at?->toIso8601String(),
                'last_active_at' => $u->last_active_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
