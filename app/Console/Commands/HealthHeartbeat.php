<?php

namespace App\Console\Commands;

use App\Jobs\HealthProbe;
use App\Services\SiteHealth;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthHeartbeat extends Command
{
    protected $signature = 'riko:health-heartbeat';

    protected $description = 'Record scheduler runtime and enqueue a harmless worker probe';

    public function handle(): int
    {
        SiteHealth::record('scheduler', SiteHealth::runtime());
        $connection = config('queue.default');
        // A synchronous/no-op driver cannot prove a background worker is running.
        if (in_array(config("queue.connections.$connection.driver"), ['sync', 'null', 'deferred', 'background', 'failover', null], true)) {
            $this->warn('Configure a persistent asynchronous queue to verify a worker.');

            return self::SUCCESS;
        }
        // Limit an offline worker to one probe every five minutes.
        DB::transaction(function () use ($connection) {
            DB::table('system_health')->insertOrIgnore(['name' => 'queue_dispatch', 'details' => '{}', 'observed_at' => '2000-01-01 00:00:00']);
            $last = DB::table('system_health')->where('name', 'queue_dispatch')->lockForUpdate()->first();
            if (Carbon::parse($last->observed_at)->gt(now()->subMinutes(5))) {
                return;
            }
            HealthProbe::dispatch()->onConnection($connection);
            SiteHealth::record('queue_dispatch', ['connection' => $connection]);
        });
        $this->info('Scheduler heartbeat recorded; worker probe scheduled when due.');

        return self::SUCCESS;
    }
}
