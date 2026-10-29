<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $u)
<url><loc>{{ $u['loc'] }}</loc>@if ($u['mod'])<lastmod>{{ $u['mod'] }}</lastmod>@endif
@foreach ($u['alts'] as $code => $href)<xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}"/>
@endforeach
@foreach ($u['images'] as $img)<image:image><image:loc>{{ $img['loc'] }}</image:loc><image:title>{{ $img['title'] }}</image:title></image:image>
@endforeach
</url>
@endforeach
</urlset>
