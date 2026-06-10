<?php

namespace Config;

use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
use CodeIgniter\Tasks\Scheduler;

/**
 * Tasks
 * -------------------------------------------------------------
 * Requires: codeigniter4/tasks (composer require codeigniter4/tasks)
 *
 * Then add ONE cron entry on the server:
 *
 *   * * * * *  cd /var/www/mosbat-ai && /usr/bin/php spark tasks:run >> writable/logs/tasks.log 2>&1
 *
 * CI4 will fan out and trigger heartbeat:run exactly at 17:00.
 *
 * Don't have the tasks library? Skip this file and use the raw
 * cron entry shown inside app/Commands/Heartbeat.php instead.
 */
class Tasks extends BaseTasks
{
    public function init(Scheduler $schedule): void
    {
        // Daily 5:00 PM heartbeat — Positive Nation CEO
        $schedule->command('heartbeat:run --slug=pn-ceo')
                 ->daily('17:00')
                 ->named('pn-ceo-heartbeat');

        // Daily 5:30 PM board report — all companies, emailed to the Chairman
        $schedule->command('report:board')
                 ->daily('17:30')
                 ->named('daily-board-report');
    }
}
