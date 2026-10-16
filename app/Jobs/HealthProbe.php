<?php

namespace App\Jobs;

use App\Services\SiteHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HealthProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function handle(): void
    {
        SiteHealth::record('queue', SiteHealth::runtime() + ['connection' => $this->connection]);
    }
}
