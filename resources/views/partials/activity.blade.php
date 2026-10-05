@forelse($items as $op)
<div class="activity-row"><span class="circle-icon"><x-icon :name="$op->type==='transfer'?'pay':($op->asset==='RC'?'earn':'swap')" :size="18"/></span><div class="details"><strong>{{ ucwords(str_replace('_',' ',$op->type)) }}</strong><small>{{ \Carbon\Carbon::parse($op->created_at)->format('d M, h:i A') }} · {{ substr($op->id,0,8) }}</small></div><div class="amount">{{ number_format((float)$op->amount,config('riko.assets')[$op->asset]) }} {{ $op->asset }}<br><span class="status {{ $op->status }}">{{ $op->status }}</span></div></div>
@empty
<div class="empty"><x-icon name="wallet" :size="32"/><h3>A fresh start.</h3><p>Your wallet activity will appear here.</p></div>
@endforelse
