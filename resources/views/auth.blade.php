@extends('layout')
@section('title',($mode==='login'?'Welcome back':'Create your account').' · RikoWallet')
@section('content')
<div class="auth-page"><div class="auth-art"><a href="/" class="brand"><span class="brandmark">R</span>RikoWallet</a><div><div class="eyebrow">ONE WALLET. YOUR WORLD.</div><h1>A little simpler.<br>A lot more <span style="color:#aa7bdc">you.</span></h1><p>Move money, manage your currencies and discover more possibilities.</p><div class="card-art"><div class="card-top">RikoWallet <small>PREPAID / CONCEPT</small></div><div class="chip"></div><div class="card-number">•••• &nbsp; •••• &nbsp; •••• &nbsp; 4821</div><div class="card-bottom">YOUR EVERYDAY WALLET <b>R.</b></div></div></div><small class="muted">Your wallet. Your pace. Your possibilities.</small></div>
<div class="auth-form"><div class="eyebrow">WELCOME TO RIKOWALLET</div><h1>{{ $mode==='login'?'Good to see you again.':'Your world starts here.' }}</h1><p>{{ $mode==='login'?'Sign in to your personal wallet.':'Create an account in a few simple steps.' }}</p>
@if($errors->any())<div class="notice error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="/{{ $mode }}">@csrf
@if($mode==='register')<div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>@endif
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required></div>
<div class="field"><label for="password">Password</label><input id="password" type="password" name="password" placeholder="{{ $mode==='register'?'At least 12 characters, letters and numbers':'Enter your password' }}" autocomplete="{{ $mode==='register'?'new-password':'current-password' }}" required></div>
@if($mode==='register')<div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div><div class="field"><label>Country of residence</label><select name="country">@foreach(config('riko.countries') as $code=>$country)<option value="{{ $code }}">{{ $country }}</option>@endforeach</select><p class="helper">Available methods depend on your verified residence.</p></div>@endif
<button class="button full">{{ $mode==='login'?'Sign in':'Create account' }}<x-icon name="arrow" :size="17"/></button></form>
<div class="foot">{{ $mode==='login'?'New here?':'Already have an account?' }} <a class="text-link" href="{{ $mode==='login'?'/register':'/login' }}">{{ $mode==='login'?'Create an account':'Sign in' }}</a></div>
@if(config('riko.sandbox'))<p class="helper" style="text-align:center;margin-top:30px">Sandbox workspace · No real funds</p>@endif
</div></div>
@endsection

