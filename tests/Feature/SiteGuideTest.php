<?php

namespace Tests\Feature;

use App\Jobs\HealthProbe;
use App\Models\User;
use App\Services\SiteHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_is_admin_only_and_never_exposes_secrets(): void
    {
        $this->get('/admin/site-guide')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/site-guide')->assertForbidden();
        $user->role = 'admin';
        $user->save();
        config(['riko.callback_secret' => 'private-callback-marker-do-not-show']);
        $this->get('/admin/site-guide')->assertOk()->assertSee('Your site, checked.')
            ->assertSee('Never observed')->assertSee('Not verified')
            ->assertDontSee('private-callback-marker-do-not-show')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_recent_and_stale_heartbeats_are_distinguished_and_connection_must_match(): void
    {
        config(['queue.default' => 'database']);
        SiteHealth::record('scheduler', SiteHealth::runtime());
        SiteHealth::record('queue', SiteHealth::runtime() + ['connection' => 'database']);
        $checks = collect(app(SiteHealth::class)->report()['checks'])->keyBy('name');
        $this->assertSame('pass', $checks['Scheduler heartbeat']['status']);
        $this->assertSame('pass', $checks['Queue worker probe']['status']);
        $this->travel(11)->minutes();
        $checks = collect(app(SiteHealth::class)->report()['checks'])->keyBy('name');
        $this->assertSame('fail', $checks['Scheduler heartbeat']['status']);
        $this->assertSame('fail', $checks['Queue worker probe']['status']);
        $this->travelBack();
        config(['queue.default' => 'sync']);
        $checks = collect(app(SiteHealth::class)->report()['checks'])->keyBy('name');
        $this->assertSame('fail', $checks['Queue worker probe']['status']);
    }

    public function test_scheduler_enqueues_probe_without_falsely_marking_worker_as_running(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $this->artisan('riko:health-heartbeat')->assertSuccessful();
        $this->artisan('riko:health-heartbeat')->assertSuccessful();
        Queue::assertPushed(HealthProbe::class, 1);
        $this->assertDatabaseHas('system_health', ['name' => 'scheduler']);
        $this->assertDatabaseMissing('system_health', ['name' => 'queue']);
        $this->travel(6)->minutes();
        $this->artisan('riko:health-heartbeat')->assertSuccessful();
        Queue::assertPushed(HealthProbe::class, 2);
        $this->travelBack();
    }

    public function test_actual_queued_job_records_worker_evidence_without_financial_changes(): void
    {
        config(['queue.default' => 'database']);
        $this->artisan('riko:health-heartbeat')->assertSuccessful();
        $this->assertDatabaseMissing('system_health', ['name' => 'queue']);
        $job = Queue::connection('database')->pop();
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $this->assertDatabaseHas('system_health', ['name' => 'queue']);
        $this->assertDatabaseCount('operations', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_sync_queue_does_not_generate_false_worker_evidence(): void
    {
        config(['queue.default' => 'sync']);
        $this->artisan('riko:health-heartbeat')->assertSuccessful();
        $this->assertDatabaseMissing('system_health', ['name' => 'queue']);
        $this->assertDatabaseMissing('system_health', ['name' => 'queue_dispatch']);
    }

    public function test_missing_monitor_table_has_a_clear_migration_instruction(): void
    {
        Schema::drop('system_health');
        $checks = collect(app(SiteHealth::class)->report()['checks'])->keyBy('name');
        $this->assertSame('fail', $checks['Monitoring table']['status']);
        $this->assertSame('unknown', $checks['Queue worker probe']['status']);
        $this->assertSame('php artisan migrate --force', $checks['Monitoring table']['fix']);
    }
}
