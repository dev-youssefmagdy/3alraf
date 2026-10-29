@php
    // Self-contained (no DB/layout dependencies beyond the optional tenant) so it still renders during a 500.
    $code = (int) ($code ?? 500);
    $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
    $supported = ['ar', 'en'];
    $seg = request()->segment(1);
    $locale = in_array($seg, $supported, true) ? $seg : ($tenant && in_array($tenant->default_locale, $supported, true) ? $tenant->default_locale : 'ar');
    $key = \Illuminate\Support\Facades\Lang::has("errors.$code", $locale) ? (string) $code : 'generic';
    $title = __("errors.$key.title", [], $locale);
    $text = __("errors.$key.text", [], $locale);
    $rtl = $locale === 'ar';
    $homeUrl = $tenant ? url('/'.($locale)) : central_url('/');
    $name = $tenant->name ?? config('app.name');
    $retryable = in_array($code, [419, 429, 500, 503], true);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $code }} · {{ $title }} | {{ $name }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:linear-gradient(160deg,#fff9f7,#fbeaf1);color:#45313b;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial,sans-serif}
        header{padding:20px 24px;text-align:center;font-weight:800;font-size:20px;color:#a8456c}
        main{flex:1;display:grid;place-items:center;padding:16px}
        .card{width:100%;max-width:480px;background:#fff;border-radius:20px;padding:40px 28px;text-align:center;box-shadow:0 10px 40px rgba(168,69,108,.12)}
        .code{font-size:84px;line-height:1;font-weight:800;background:linear-gradient(135deg,#c96f91,#a8456c);-webkit-background-clip:text;background-clip:text;color:transparent}
        h1{font-size:24px;margin:14px 0 8px}p{color:#806b76;line-height:1.8;margin:0}
        .hint{font-size:13px;margin-top:14px;color:#a0909a}
        .actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:26px}
        a,button{font:inherit;cursor:pointer;display:inline-block;padding:11px 24px;border-radius:12px;font-weight:700;text-decoration:none;border:2px solid #c96f91}
        .primary{background:#c96f91;color:#fff}.ghost{background:#fff;color:#a8456c}
        footer{padding:16px;text-align:center;font-size:12px;color:#a0909a}
    </style>
</head>
<body>
    <header><img src="/logo-sm.webp" alt="{{ $name }}" style="height:44px;width:auto"></header>
    <main>
        <div class="card" role="alert">
            <div class="code">{{ $code }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $text }}</p>
            @if ($code >= 500)<p class="hint">{{ __('errors.contact', [], $locale) }}</p>@endif
            <div class="actions">
                <a class="primary" href="{{ $homeUrl }}">{{ __('errors.home', [], $locale) }}</a>
                @if ($retryable)<button type="button" class="ghost" onclick="location.reload()">{{ __('errors.retry', [], $locale) }}</button>@endif
            </div>
        </div>
    </main>
    <footer>&copy; {{ date('Y') }} {{ $name }}</footer>
</body>
</html>
