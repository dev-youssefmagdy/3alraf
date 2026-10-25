<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.site.not_found', [], 'ar') }}</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fff9f7;color:#45313b;font-family:Tahoma,Arial,sans-serif;text-align:center;padding:16px}
        .box{max-width:420px}h1{font-size:28px;margin:12px 0}p{color:#806b76;line-height:1.8}
        a{display:inline-block;margin-top:16px;padding:10px 22px;background:#c96f91;color:#fff;border-radius:10px;text-decoration:none;font-weight:700}
        .code{font-size:64px;font-weight:800;color:#c96f91}
    </style>
</head>
<body>
    <div class="box">
        <div class="code">404</div>
        <h1>{{ __('admin.site.not_found', [], 'ar') }}</h1>
        <p>{{ __('admin.site.not_found_text', [], 'ar') }}</p>
        <a href="{{ central_url('/') }}">{{ __('admin.site.back_central', [], 'ar') }}</a>
    </div>
</body>
</html>
