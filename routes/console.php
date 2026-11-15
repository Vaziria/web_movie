<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs one server cron entry: `* * * * * php artisan schedule:run` (see docs/DEPLOY.md).
Schedule::command('app:push-send')->everyMinute()->withoutOverlapping(10);

// Daily active visitor rows (Admin → Statistics) are kept for about 13 months.
Schedule::call(fn () => DB::table('daily_actives')
    ->where('day', '<', now()->subDays((int) config('movies.activity_days_keep'))->toDateString())->delete())
    ->daily()->name('prune-daily-actives');
