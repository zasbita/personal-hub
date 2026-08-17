<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bot:notify')->everyFifteenMinutes();
