<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\BackupDatabase::class,
        Commands\SyncBiometricPunches::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (config('google-drive.scheduled_backup')) {
            $schedule->command('backup:run')->dailyAt('23:00');
        }

        $schedule->command('dtr:sync-punches')->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('dtr:sync-employees --create-users')->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
