<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateDailyMemos;


// Existing inspire command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule attendance auto-marking - runs every hour
Schedule::command('attendance:mark-absent')->hourly();

// Schedule the memo generation
Schedule::command(GenerateDailyMemos::class)
    ->dailyAt('18:00')
    ->description('Generate memos for absent trainees')
    ->emailOutputTo('admin@example.com'); // Optional

// For testing - run every minute
if (app()->environment('local')) {
    Schedule::command(GenerateDailyMemos::class)
        ->everyMinute()
        ->description('Test memo generation');
}