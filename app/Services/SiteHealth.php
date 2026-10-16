<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SiteHealth
{
    public const EXTENSIONS = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'json', 'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'reflection', 'session', 'spl', 'tokenizer', 'xml', 'zip'];

    public static function runtime(): array
    {
        return ['php' => PHP_VERSION, 'missing' => array_values(array_filter(self::EXTENSIONS, fn ($extension) => ! extension_loaded($extension)))];
    }

    public static function record(string $name, array $details = []): void
    {
        DB::table('system_health')->updateOrInsert(['name' => $name], ['details' => json_encode($details, JSON_THROW_ON_ERROR), 'observed_at' => now()]);
    }

    public function report(): array
    {
        $checks = [];
        $add = function ($group, $name, $status, $detail, $fix = '') use (&$checks) {
            $checks[] = compact('group', 'name', 'status', 'detail', 'fix');
        };
        $add('PHP & extensions', 'Web PHP version', version_compare(PHP_VERSION, '8.2', '>=') ? 'pass' : 'fail', PHP_VERSION.' · '.PHP_SAPI, 'Use PHP 8.2+ for both website and CLI. PHP 8.3 is supported.');
        foreach (self::EXTENSIONS as $extension) {
            $add('PHP & extensions', $extension, extension_loaded($extension) ? 'pass' : 'fail', extension_loaded($extension) ? 'Loaded in web runtime' : 'Missing in this runtime', 'Enable this extension for the website PHP and CLI PHP, then restart PHP through your hosting panel.');
        }
        $add('Application', 'Laravel dependencies', 'pass', 'Laravel '.app()->version().' booted successfully.', 'After pulling dependencies: composer install --no-dev --prefer-dist --optimize-autoloader');
        $upload = ini_get('upload_max_filesize');
        $post = ini_get('post_max_size');
        $uploadsOk = filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN) && ini_parse_quantity($upload) >= 5 * 1024 * 1024 && (ini_parse_quantity($post) === 0 || ini_parse_quantity($post) >= 6 * 1024 * 1024);
        $add('PHP & extensions', 'File upload limits', $uploadsOk ? 'pass' : 'warn', 'upload_max_filesize='.$upload.'; post_max_size='.$post, 'KYC accepts up to 5 MB. Enable file_uploads, set upload_max_filesize=10M and post_max_size=12M in web PHP; also check the host request-body limit.');
        $opcache = extension_loaded('Zend OPcache') && filter_var(ini_get(PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable'), FILTER_VALIDATE_BOOLEAN);
        $add('PHP & extensions', 'OPcache (recommended)', $opcache ? 'pass' : 'warn', $opcache ? 'Enabled in this runtime' : 'Not enabled in this runtime', 'Enable OPcache in production web PHP for faster responses; it is optional for CLI.');
        $add('Application', 'Production environment', app()->environment('production') ? 'pass' : 'warn', app()->environment(), 'For live hosting set APP_ENV=production.');
        $add('Application', 'Debug disabled', config('app.debug') ? 'fail' : 'pass', config('app.debug') ? 'Debug is enabled' : 'Debug is disabled', 'Set APP_DEBUG=false and rebuild config cache.');
        $add('Application', 'Sandbox disabled', config('riko.sandbox') ? 'warn' : 'pass', config('riko.sandbox') ? 'Sandbox mode' : 'Live mode configured', 'For live hosting set RIKO_SANDBOX=false. This does not activate unfinished integrations.');
        $add('Application', 'Encryption key', config('app.key') ? 'pass' : 'fail', config('app.key') ? 'Configured (value hidden)' : 'Missing', 'On a fresh installation only: php artisan key:generate. Never replace an existing production key.');
        $add('Application', 'HTTPS configuration', str_starts_with((string) config('app.url'), 'https://') && request()->isSecure() ? 'pass' : 'warn', request()->isSecure() ? 'Current request uses HTTPS' : 'Current request is not recognized as HTTPS', 'Set APP_URL=https://your-domain and configure HTTPS/trusted proxy correctly. Certificate renewal must be checked at the host.');
        $add('Application', 'Secure session cookie', config('session.secure') ? 'pass' : 'warn', config('session.secure') ? 'Enabled' : 'Disabled', 'For HTTPS production set SESSION_SECURE_COOKIE=true.');
        foreach (['Configuration cache' => app()->configurationIsCached(), 'Route cache' => app()->routesAreCached()] as $name => $ready) {
            $add('Application', $name, $ready ? 'pass' : 'warn', $ready ? 'Present' : 'Not built', 'php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache');
        }
        foreach (['storage' => storage_path(), 'storage/framework/views' => storage_path('framework/views'), 'storage/framework/cache' => storage_path('framework/cache'), 'storage/logs' => storage_path('logs'), 'bootstrap/cache' => base_path('bootstrap/cache'), 'storage/app/private' => storage_path('app/private')] as $name => $path) {
            $add('Storage & cache', $name, is_dir($path) && is_writable($path) ? 'pass' : 'fail', is_dir($path) && is_writable($path) ? 'Writable by current PHP process' : 'Missing or not writable', 'Grant the web and queue users write access to runtime directories; do not use chmod 777.');
        }
        try {
            DB::select('SELECT 1');
            $driver = DB::connection()->getDriverName();
            $add('Database', 'Database connection', $driver === 'mysql' ? 'pass' : 'warn', 'Connected · '.$driver, 'Production requires the configured MySQL-compatible database.');
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php')) ?: []);
            $pending = count(array_diff($files, $ran));
            $add('Database', 'Migrations', $pending ? 'fail' : 'pass', $pending.' pending migrations', 'Back up first, then run php artisan migrate --force.');
        } catch (Throwable $e) {
            $add('Database', 'Database / migration check', 'fail', 'Could not verify. Connection details are hidden.', 'Check database settings and server logs, then run php artisan migrate --force.');
        }
        $key = 'site-health:'.Str::uuid();
        try {
            Cache::put($key, 'probe', 30);
            $ok = Cache::get($key) === 'probe';
            Cache::forget($key);
            $persistent = ! in_array(config('cache.default'), ['array', 'null'], true);
            $add('Storage & cache', 'Cache read/write', $ok && $persistent ? 'pass' : 'fail', $ok ? 'Read/write succeeded · '.config('cache.default') : 'Read/write failed', 'Configure a persistent cache store shared by web and workers.');
        } catch (Throwable $e) {
            $add('Storage & cache', 'Cache read/write', 'fail', 'Cache test failed; connection details hidden.', 'Check CACHE_STORE, cache tables and runtime permissions.');
        }
        $rows = collect();
        try {
            $rows = DB::table('system_health')->get()->keyBy('name');
        } catch (Throwable $e) {
            $add('Background jobs', 'Monitoring table', 'fail', 'Heartbeat storage unavailable.', 'php artisan migrate --force');
        }
        $connection = config('queue.default');
        $async = ! in_array(config("queue.connections.$connection.driver"), ['sync', 'null', 'deferred', 'background', 'failover', null], true);
        $add('Background jobs', 'Queue connection', $async ? 'pass' : 'fail', (string) $connection, 'Use QUEUE_CONNECTION=database (or a supported persistent queue), then restart workers.');
        foreach (['scheduler' => 180, 'queue' => 600] as $name => $seconds) {
            $row = $rows->get($name);
            $details = $row ? json_decode($row->details, true) : [];
            $age = $row ? now()->timestamp - Carbon::parse($row->observed_at)->timestamp : null;
            $valid = $age !== null && $age >= 0 && $age <= $seconds;
            if ($name === 'queue') {
                $valid = $valid && $async && ($details['connection'] ?? null) === $connection;
            }
            $last = $row ? Carbon::parse($row->observed_at)->timezone('Asia/Kolkata')->format('d M Y H:i:s').' IST' : 'Never observed';
            $add('Background jobs', $name === 'scheduler' ? 'Scheduler heartbeat' : 'Queue worker probe', $valid ? 'pass' : ($row ? 'fail' : 'unknown'), $last.($valid ? ' · recent successful execution' : ' · no recent verified execution'), $name === 'scheduler' ? 'Run php artisan schedule:run every minute using hosting cron. A manual heartbeat only proves that one execution.' : 'Run php artisan queue:work continuously on the default connection/default queue. Allow up to 5 minutes after starting cron; refresh this page.');
            if ($row) {
                $runtimeOk = version_compare($details['php'] ?? '0', '8.2', '>=') && empty($details['missing']);
                $add('Background jobs', ucfirst($name).' PHP/extensions', $valid && $runtimeOk ? 'pass' : 'warn', 'Last observed PHP '.($details['php'] ?? 'unknown').'; missing: '.implode(', ', $details['missing'] ?? []).($valid ? '' : ' (stale/unverified)'), 'CLI and web PHP can differ. Check php -v and php -m using the executable configured in cron/worker.');
            }
        }
        try {
            $failed = DB::table('failed_jobs')->count();
            $add('Background jobs', 'Failed queue jobs', $failed ? 'warn' : 'pass', $failed.' recorded failures', 'Review php artisan queue:failed and logs. Do not retry financial jobs blindly.');
            $day = now()->timezone('Asia/Kolkata')->subDay()->toDateString();
            $stats = DB::table('daily_stats')->where('day', $day)->exists();
            $add('Background jobs', 'Daily statistics', $stats ? 'pass' : 'warn', $stats ? 'Yesterday has a statistics record' : 'Yesterday has no statistics record yet', 'Scheduled at 00:15 Asia/Kolkata. A fresh site may not have a record until its first run.');
        } catch (Throwable $e) {
            $add('Background jobs', 'Job tables', 'unknown', 'Could not read job/statistics tables.', 'Run migrations and inspect private server logs.');
        }
        $add('Manual verification', 'Email delivery', 'unknown', in_array(config('mail.default'), ['log', 'array'], true) ? 'Current mailer does not send email' : 'Mailer configured; delivery is unverified', 'Configure mail and verify actual delivery once email workflows are ready.');
        $add('Manual verification', 'Backups, HTTPS renewal & security review', 'unknown', 'Cannot verify automatically from this page.', 'Verify database/private-storage/APP_KEY backups, restore tests, certificate renewal and security review at the host.');
        $add('Manual verification', 'Earning partners & payout readiness', 'unknown', 'Technical checks do not certify live earning or payout readiness.', 'Configure trusted task providers, funded rewards, KYC, payout rates/methods and approvals. Review PENDING-WORK.txt.');

        return ['checks' => $checks, 'counts' => array_count_values(array_column($checks, 'status'))];
    }
}
