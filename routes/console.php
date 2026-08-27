<?php

use Illuminate\Support\Facades\Schedule;

// withoutOverlapping: a hung HTTP call must not let the next run send the same
// notification twice.
Schedule::command('bot:schedule')->hourly()->withoutOverlapping();
Schedule::command('bot:notify')->everyFifteenMinutes()->withoutOverlapping();

// app.timezone is UTC; the digest should land on Monday morning where it is read.
Schedule::command('bot:digest')
    ->weeklyOn(1, '07:00')
    ->timezone(config('app.display_timezone', 'Asia/Jakarta'));

Schedule::command('expenses:recurring')->dailyAt('07:00')->timezone(config('app.display_timezone', 'Asia/Jakarta'))->withoutOverlapping();
