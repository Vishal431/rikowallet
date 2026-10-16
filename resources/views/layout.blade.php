<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','RikoWallet — Your money, your world')</title><link rel="stylesheet" href="/assets/riko.css"><script src="/assets/riko.js" defer></script></head>
<body>
@php $isAdmin=request()->is('admin*'); $logged=auth()->check(); $enabled=$data['earning_enabled']??(auth()->check()?app(\App\Services\Settings::class)->policy()['earn_web']:false); @endphp
@if($logged && !request()->is('/'))
<aside class="sidebar">
<a class="brand" href="/app"><span class="brandmark">R</span>RikoWallet<span class="brand-dot">.</span></a>
<div class="side-caption">{{ $isAdmin?'WORKSPACE / ADMIN':'YOUR EVERYDAY WALLET' }}</div>
<nav>
@if($isAdmin)
@foreach(['overview'=>['chart','Overview'],'users'=>['user','All users'],'kyc'=>['shield','KYC & access'],'operations'=>['swap','Transactions'],'withdrawals'=>['up','Manual withdrawals'],'methods'=>['wallet','Payment methods'],'earning'=>['earn','Earning & tasks'],'ads'=>['settings','Ad Networks'],'referrals'=>['pay','Referral rules'],'contest'=>['trophy','Referral contest'],'liquidity'=>['chart','Swap & liquidity'],'support'=>['chat','Support inbox'],'cron'=>['globe','Cron & jobs'],'audit'=>['file','Audit history'],'providers'=>['settings','Providers'],'site-guide'=>['shield','Site guide']] as $key=>$item)
<a class="{{ ($page??'')===$key?'active':'' }}" href="/admin/{{ $key }}"><x-icon :name="$item[0]"/>{{ $item[1] }}</a>
@endforeach
@else
@foreach(['home'=>['home','Overview'],'pay'=>['pay','Payments'],'cards'=>['card','My cards'],'earn'=>['earn','Earn RikoCoin'],'business'=>['file','Business'],'activity'=>['chart','Activity'],'support'=>['chat','Contact support'],'profile'=>['settings','Settings']] as $key=>$item)
@if($key!=='earn'||$enabled)<a class="{{ ($page??'')===$key?'active':'' }}" href="/app/{{ $key }}"><x-icon :name="$item[0]"/>{{ $item[1] }}@if($key==='earn')<span class="tiny-tag">RC</span>@endif</a>@endif
@endforeach
@endif
</nav>
<div class="sidebar-bottom"><div class="secure-note"><x-icon name="shield"/><span>Built around you.<small>{{ config('riko.sandbox')?'Sandbox · No real funds':'Protected wallet workspace' }}</small></span></div><a class="account-mini" href="/app/profile"><span class="avatar">{{ substr(auth()->user()->name,0,1) }}</span><span>{{ auth()->user()->name }}<small>ID {{ auth()->user()->wallet_id }}</small></span><x-icon name="chevron" :size="16"/></a></div>
</aside>
<div class="workspace">
<header class="topbar"><a class="brand mobile-brand" href="/app"><span class="brandmark">R</span>RikoWallet</a><div class="breadcrumb">{{ $isAdmin?'Admin workspace':'Personal workspace' }}<span>/</span>{{ ucfirst(str_replace('-',' ',$page??'Overview')) }}</div><div class="top-actions"><span class="sandbox-dot">{{ config('riko.sandbox')?'Sandbox mode':'Secure session' }}</span><a class="icon-button" href="/app/activity" aria-label="Activity"><x-icon name="bell"/></a><a class="avatar" href="/app/profile">{{ substr(auth()->user()->name,0,1) }}</a></div></header>
<main class="main-content">
@if(session('success'))<div class="notice success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><x-icon name="shield"/><div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>@endif
@yield('content')
</main></div>
@if(!$isAdmin)
<nav class="mobile-dock" aria-label="Main navigation">
@foreach(['home'=>['home','Home'],'pay'=>['pay','Pay'],'cards'=>['card','Cards'],'earn'=>['earn','Earn'],'profile'=>['user','Profile']] as $key=>$item)
@if($key!=='earn'||$enabled)<a aria-label="{{ $item[1] }}" href="/app/{{ $key }}" class="{{ ($page??'')===$key||($key==='earn'&&in_array($page??'',['referrals','contest']))?'active':'' }}"><span><x-icon :name="$item[0]"/></span><b>{{ $item[1] }}</b></a>@endif
@endforeach
</nav>
@endif
@else
@yield('content')
@endif
</body></html>



