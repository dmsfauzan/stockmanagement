<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:alerts')->dailyAt('07:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('inventory:digest')->dailyAt('07:05')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('inventory:cycle-count')->weeklyOn(1, '08:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('reports:mail --period=daily')->dailyAt('06:30')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('reports:mail --period=weekly')->weeklyOn(1, '06:45')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('reports:mail --period=monthly')->monthlyOn(1, '07:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('accounting:export --period=daily')->dailyAt('03:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('accounting:export --period=monthly')->monthlyOn(1, '03:30')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('inventory:archive')->weeklyOn(0, '02:30')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('security:purge')->dailyAt('02:45')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('security:resolve-geo')->hourly()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('idempotency:purge')->dailyAt('04:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('backup:run --only-db')->dailyAt('01:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('backup:clean')->dailyAt('02:00')->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('backup:monitor')->dailyAt('01:30')->withoutOverlapping()->onOneServer()->runInBackground();
