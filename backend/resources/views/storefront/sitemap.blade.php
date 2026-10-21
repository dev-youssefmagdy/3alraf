<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $u)
<url><loc>{{ $u['loc'] }}</loc>@if ($u['mod'])<lastmod>{{ $u['mod'] }}</lastmod>@endif</url>
@endforeach
</urlset>
