<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SendClassReminders::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Run every minute to check for classes starting in 30 minutes
        $schedule->command('class:send-reminders')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/class-reminders.log'));
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
