<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Numbers for Admin → Statistics and GET /api/stats. Days are in the admin time zone (WIB). */
class Stats
{
    private function tz(): string
    {
        return (string) config('movies.admin_timezone');
    }

    private function today(): Carbon
    {
        return now()->timezone($this->tz())->startOfDay();
    }

    /** @return array<int, array{day: string, users: int, guests: int, signups: int}> oldest first */
    public function daily(int $days = 30): array
    {
        $from = $this->today()->subDays($days - 1);
        $rows = DB::table('daily_actives')->where('day', '>=', $from->toDateString())
            ->selectRaw('day, is_user, count(*) as n')->groupBy('day', 'is_user')->get();
        $active = [];
        foreach ($rows as $r) {
            $active[substr((string) $r->day, 0, 10)][(int) $r->is_user] = (int) $r->n;
        }

        // Sign-ups grouped in PHP so the day boundary follows the admin time zone on every database.
        $signups = User::query()->where('created_at', '>=', $from->copy()->utc())->pluck('created_at')
            ->countBy(fn ($at) => $at->copy()->timezone($this->tz())->toDateString());

        $out = [];
        for ($d = $from->copy(); $d->lte($this->today()); $d->addDay()) {
            $key = $d->toDateString();
            $out[] = ['day' => $key, 'users' => $active[$key][1] ?? 0, 'guests' => $active[$key][0] ?? 0, 'signups' => (int) ($signups[$key] ?? 0)];
        }

        return $out;
    }

    /** @return array<int, array{month: string, users: int, guests: int}> oldest first; distinct visitors per month */
    public function monthly(int $months = 12): array
    {
        $out = [];
        $start = $this->today()->startOfMonth()->subMonths($months - 1);
        for ($m = $start->copy(); $m->lte($this->today()); $m->addMonth()) {
            $range = [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()];
            $count = fn (bool $isUser) => DB::table('daily_actives')->whereBetween('day', $range)->where('is_user', $isUser)->distinct()->count('visitor');
            $out[] = ['month' => $m->format('Y-m'), 'users' => $count(true), 'guests' => $count(false)];
        }

        return $out;
    }

    /** @return array{users_total: int, users_blocked: int, google_users: int, signups_today: int, active_today_users: int, active_today_guests: int, active_month_users: int, active_month_guests: int} */
    public function totals(): array
    {
        $today = $this->today()->toDateString();
        $monthStart = $this->today()->startOfMonth()->toDateString();
        $activeToday = fn (bool $u) => DB::table('daily_actives')->where('day', $today)->where('is_user', $u)->count();
        $activeMonth = fn (bool $u) => DB::table('daily_actives')->whereBetween('day', [$monthStart, $today])->where('is_user', $u)->distinct()->count('visitor');

        return [
            'users_total' => User::count(),
            'users_blocked' => User::whereNotNull('blocked_at')->count(),
            'google_users' => User::whereNotNull('google_id')->count(),
            'signups_today' => User::where('created_at', '>=', $this->today()->utc())->count(),
            'active_today_users' => $activeToday(true),
            'active_today_guests' => $activeToday(false),
            'active_month_users' => $activeMonth(true),
            'active_month_guests' => $activeMonth(false),
        ];
    }
}
