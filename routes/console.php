<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|------------------------------------------------------------------------------
| Scheduled Tasks
|------------------------------------------------------------------------------
| The VPS runs `php artisan schedule:run` every minute via cron; Laravel then
| decides what is actually due. See README for the cron entry.
*/

// Cancel pending enquiries nobody has answered, so a popular date doesn't keep
// showing a stale "another enquiry is pending" notice forever.
Schedule::command('bookings:expire-stale')
    ->dailyAt('03:00')
    ->timezone(config('app.timezone'))
    ->onOneServer();
