@extends('layout')
@section('title','Site guide · RikoWallet Admin')
@section('content')
<div class="admin-menu"><a href="/admin/overview">Overview</a><a href="/admin/site-guide">Site guide</a><a href="/admin/cron">Cron & jobs</a><a href="/admin/withdrawals">Manual withdrawals</a></div>
<div class="page-head"><div><div class="eyebrow">RIKOWALLET / SETUP CHECKLIST</div><h1>Your site, checked.</h1><p>Requirements, background jobs and next steps in one place.</p></div><a class="btn" href="/admin/site-guide">Refresh checks</a></div>
<div class="notice {{ ($health['counts']['fail']??0) ? 'error' : 'success' }}"><div><strong>{{ ($health['counts']['fail']??0) ? 'Setup needs attention' : 'No failures detected in available checks' }}</strong><div>Checked {{ now()->timezone('Asia/Kolkata')->format('d M Y, H:i:s') }} IST. This is a technical checklist, not confirmation that every feature is ready for real users.</div></div></div>
<div class="health-summary">
@foreach(['pass'=>'Passed','fail'=>'Needs fixing','warn'=>'Review','unknown'=>'Not verified'] as $status=>$label)
<div class="panel"><span class="health-status health-{{ $status }}">{{ $label }}</span><h2>{{ $health['counts'][$status]??0 }}</h2></div>
@endforeach
</div>
@foreach(collect($health['checks'])->groupBy('group') as $group=>$checks)
<section class="panel health-section"><h2>{{ $group }}</h2>
@foreach($checks as $check)
<div class="health-row"><div><strong>{{ $check['name'] }}</strong><p>{{ $check['detail'] }}</p>@if($check['fix'])<details><summary>{{ $check['status']==='pass'?'Setup notes':'How to check / fix' }}</summary><p>{{ $check['fix'] }}</p></details>@endif</div><span class="health-status health-{{ $check['status'] }}">{{ ['pass'=>'Pass','fail'=>'Needs fixing','warn'=>'Review','unknown'=>'Not verified'][$check['status']] }}</span></div>
@endforeach
</section>
@endforeach
<section class="panel health-section"><h2>Commands for your existing server</h2><p>Run from your project directory with the correct PHP CLI version. These commands are instructions; this page never executes shell commands.</p>
<h3>After pulling an update</h3><pre class="health-code">git pull --ff-only origin main &amp;&amp; composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction &amp;&amp; php artisan optimize:clear &amp;&amp; php artisan migrate --force &amp;&amp; php artisan config:cache &amp;&amp; php artisan route:cache &amp;&amp; php artisan view:cache &amp;&amp; php artisan queue:restart</pre>
<h3>Cron · every minute</h3><p>In your hosting panel, use your actual project path and PHP CLI executable. The account must have access to the project and writable runtime directories.</p><pre class="health-code">* * * * * cd /ABSOLUTE/PROJECT/PATH &amp;&amp; /PATH/TO/php artisan schedule:run &gt;&gt; /ABSOLUTE/PROJECT/PATH/storage/logs/scheduler.log 2&gt;&amp;1</pre>
<h3>Queue · keep running with Supervisor, systemd or your hosting worker manager</h3><pre class="health-code">php artisan queue:work --sleep=3 --tries=3 --timeout=60</pre>
<p>The harmless probe uses the default queue on the configured connection. A worker listening only to a different queue will not verify this probe. One successful run does not prove the process will stay alive. Cron is fresh for 3 minutes; worker evidence is fresh for 10 minutes, with probes dispatched every 5 minutes.</p>
<h3>Inspect PHP and dependencies</h3><pre class="health-code">php -v
php -m
composer check-platform-reqs --no-dev
php artisan schedule:list
php artisan queue:failed</pre>
<p>PHP 8.2+ required; PHP 8.3 supported. Web and CLI can load different extensions. Composer validates the locked dependency requirements. The page never displays passwords, API keys, connection strings, job payloads or raw exceptions.</p>
<h3>Before launch</h3><p>Point the domain document root to <code>public/</code>. Keep <code>.env</code> and private storage inaccessible from the web. Back up the database, private files and APP_KEY; test restoration. Do not run <code>key:generate</code> again on an existing installation. Complete the remaining work in <code>PENDING-WORK.txt</code>.</p>
</section>
@endsection
