<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automated backups
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');

// Schedule borrowing reminders (already exists)
Schedule::command('borrowings:send-reminders')->daily()->at('08:00');
Schedule::command('borrowings:check-overdue')->daily()->at('09:00');
