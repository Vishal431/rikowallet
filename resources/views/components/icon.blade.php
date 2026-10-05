@props(['name'=>'wallet','size'=>22])
@php
$paths=[
'home'=>'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
'pay'=>'m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3',
'wallet'=>'M3 7V5a2 2 0 0 1 2-2h13v4M3 7h17a1 1 0 0 1 1 1v12H5a2 2 0 0 1-2-2V7Zm18 5h-5v4h5',
'card'=>'M3 5h18v14H3V5Zm0 5h18M6 15h4',
'earn'=>'M3 8h18v4H3V8Zm2 4v9h14v-9M12 8v13M12 8C4 8 5 1 9 3c2 1 3 5 3 5Zm0 0c8 0 7-7 3-5-2 1-3 5-3 5Z',
'user'=>'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2Z',
'plus'=>'M12 5v14M5 12h14',
'down'=>'M12 3v16m-6-6 6 6 6-6',
'up'=>'M12 21V5m-6 6 6-6 6 6',
'swap'=>'M3 7h17m-5-5 5 5-5 5M21 17H4m5-5-5 5 5 5',
'arrow'=>'M5 12h14m-5-5 5 5-5 5',
'chevron'=>'m9 5 7 7-7 7',
'bell'=>'M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8M9 21h6',
'search'=>'M16 10a6 6 0 1 1-12 0 6 6 0 0 1 12 0Zm-1 5 6 6',
'shield'=>'m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6Zm-5 10 3 3 7-7',
'lock'=>'M5 10h14v11H5V10Zm3 0V6a4 4 0 0 1 8 0v4M12 14v3',
'chat'=>'M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4ZM8 10h8M8 14h5',
'globe'=>'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z',
'phone'=>'M7 2h10v20H7V2Zm3 16h4',
'copy'=>'M8 8h13v13H8V8ZM16 8V3H3v13h5',
'check'=>'m5 12 4 4 10-10',
'chart'=>'M4 3v18h18M8 16v-5m5 5V7m5 9V4',
'file'=>'M5 2h9l5 5v15H5V2Zm9 0v6h5M8 12h8m-8 4h6',
'trophy'=>'M7 3h10v6a5 5 0 0 1-10 0V3Zm0 2H3v3a4 4 0 0 0 4 4m10-7h4v3a4 4 0 0 1-4 4M12 14v6m-5 1h10',
'settings'=>'M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8ZM9 2h6l1 4 4 1 2 5-3 3 0 5-5 2-3-3-5 0-2-5 3-3Z',
'logout'=>'M9 3H3v18h6M9 12h12m-5-5 5 5-5 5',
'star'=>'m12 2 3 6 7 1-5 5 1 8-6-4-6 4 1-8-5-5 7-1Z'
];
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name]??$paths['wallet'] }}"/></svg>

