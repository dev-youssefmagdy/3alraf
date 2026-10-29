# SEO, Search Console & Analytics — setup guide

Everything below is configured per store in **Admin panel → Reports & Settings → SEO & Analytics**
(`/admin/seo`, permission `settings.view` to open, `settings.edit` to save).
Settings are stored in `tenants.settings.seo` (encrypted JSON column), validated by
`App\Http\Requests\Tenant\SeoSettingsRequest` and read through `App\Services\SeoSettingsService`.

## What the platform already does for you (no setup)

| Area | Behaviour |
|---|---|
| Titles & descriptions | `Page title \| Store name`, 158-char description generated from the page; home page uses your own text per language |
| Canonical URLs | One canonical per page (pagination keeps `?page=N`; filters/sort/search params are dropped) |
| Language SEO | `hreflang` for every store language + `x-default`, also repeated in `sitemap.xml` |
| Indexing control | Cart, checkout, account, order, login, register, affiliate pages and filtered/search results are `noindex`; everything else `index,follow,max-image-preview:large` |
| `robots.txt` | Generated per store at `/robots.txt`: blocks private paths, advertises the sitemap; `Disallow: /` while "Allow search engines" is OFF |
| `sitemap.xml` | Home, categories, brands, products (with images), bundles, static pages, blog, custom pages; `lastmod`; hreflang alternates |
| Social cards | Open Graph + Twitter card on every page (product image, category image or your default share image) |
| Structured data (JSON-LD) | `Organization`/`Store` + `WebSite` + SearchAction (home), `Product` + `Offer` + `AggregateRating` (product), `BreadcrumbList` (product, category, brand, pages, blog), `BlogPosting` (blog) |
| Accessibility / speed | Skip link, landmarks, single `h1`, alt text, sized images, LCP image preloaded with `fetchpriority=high`, lazy loading below the fold, storefront-only CSS/JS bundle |

## Step by step

### 1. Open the store to search engines
SEO & Analytics → **Search appearance** → keep *Allow search engines to index my store* ticked, write the home
title (≤ 60 characters) and description (120–160) for every language, upload/enter a default share image
(1200×630, https) and save.

### 2. Google Search Console (verification + sitemap)
1. Go to <https://search.google.com/search-console> → **Add property** → **URL prefix** → enter the store URL
   (e.g. `https://shop.example.com/`). Use the exact address customers see (https, with/without www).
2. Choose **HTML tag**. Copy only the value of `content="…"` (or paste the whole `<meta>` tag — the panel extracts it).
3. Paste it into **Search Console & Webmaster → Google Search Console** and save.
4. Back in Google click **Verify**. (Keep the code in place permanently — Google re-checks it.)
5. Search Console → **Sitemaps** → add `sitemap.xml` → Submit. Status should become *Success*.
6. Optional: **URL Inspection** → paste the home URL → *Request indexing*.

Domain-level verification (DNS TXT) is also fine and does not need the panel; you still submit the sitemap.

### 3. Bing Webmaster Tools (also feeds DuckDuckGo, Yahoo, Ecosia)
<https://www.bing.com/webmasters> → *Import from Google Search Console* (one click) **or** add the site manually,
choose *HTML Meta Tag* and paste the `msvalidate.01` content value into the Bing field. Submit `sitemap.xml`.

Yandex / Pinterest / Meta domain verification work the same way (paste the code, save, press Verify on their site).

### 4. Google Analytics 4
1. <https://analytics.google.com> → Admin → **Create → Property** → Web data stream with the store URL.
2. Copy the **Measurement ID** (`G-XXXXXXXXXX`) into **Analytics & Pixels → GA4** and save.
3. Open GA4 → **Reports → Realtime** and visit your store in another browser tab: you appear within ~30 s.
   (Logged-in admin visits are *not* tracked unless you tick "Also track my own visits".)
4. Admin → **Events** → mark `purchase` as a **key event**.
5. Admin → **Product links → Search Console links** → link the verified property to see queries inside GA4.

Events sent automatically: `page_view`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase` (with items, value, currency,
`transaction_id`; fires once per order in the browser), `search`.

### 5. Google Tag Manager (instead of GA4 directly)
Enter the **GTM container ID** (`GTM-XXXXXXX`). The same ecommerce events are pushed to `dataLayer` in GA4 format, so create
GA4 event tags triggered by custom events `view_item`, `add_to_cart`, `begin_checkout`, `purchase`.
**Do not fill both GTM and GA4** — pages would be counted twice. When GTM is set, the direct GA4/Ads snippets are skipped.

### 6. Google Ads
Enter the Ads **conversion ID** (`AW-123456789`) and the purchase **conversion label**. A `conversion` event with value,
currency and transaction id fires on every purchase.

### 7. Meta Pixel, TikTok Pixel, Snapchat Pixel, Microsoft Clarity
Paste the pixel / project ID. Standard events are mapped: ViewContent, AddToCart, InitiateCheckout, Purchase (Meta);
ViewContent, AddToCart, InitiateCheckout, CompletePayment (TikTok); VIEW_CONTENT, ADD_CART, START_CHECKOUT, PURCHASE (Snapchat).
Verify with each platform's *Test events* tool (Meta Events Manager, TikTok Events Manager, Snapchat Events Manager).
Clarity (free heat-maps / session recordings) starts recording automatically.

### 8. Business details & social profiles
Fill logo (https), phone/e-mail, optional address and social URLs. They feed the `Organization` JSON-LD
(`sameAs`), which helps Google build the brand knowledge panel and site-name results.

### 9. Validate
- Rich results: <https://search.google.com/test/rich-results> with a product URL → *Product*, *Breadcrumbs*, merchant listings valid.
- Open `https://your-store/robots.txt` and `https://your-store/sitemap.xml`.
- Search Console → **Pages** after 3–7 days: indexed vs. not indexed (cart/checkout/login should be "Excluded by noindex" — expected).
- PageSpeed Insights: <https://pagespeed.web.dev/> for mobile & desktop.

## Performance behaviour of tags
Tags never block rendering. They are injected after the first user interaction or ~3 s after the `load` event
(whichever first) and queued events are replayed, so Core Web Vitals are not affected. Visitors who leave before that
moment are not counted by third-party tools — this is the standard trade-off for a 100 PageSpeed score.
If you must count every pageview, load your tag directly via GTM instead.

## Production server checklist (outside the application)
Several Lighthouse / PageSpeed audits depend on the web server, not on the Laravel code:

1. **HTTPS everywhere** and HTTP → HTTPS 301 redirect; set `APP_URL=https://…` and `TRUSTED_PROXIES` when behind a load balancer
   (canonical, hreflang and sitemap URLs are built from the request scheme/host).
2. **HTTP/2 or HTTP/3** and **gzip/brotli** for `text/html, text/css, application/javascript, application/json, image/svg+xml, application/xml`.
3. **Long cache** for fingerprinted assets: `/build/*` → `Cache-Control: public, max-age=31536000, immutable`; images in `/media`, `/storage`, `logo-*` the same.
   A ready example lives in `docs/cdn-nginx.conf`.
4. Serve uploaded product/banner images as WebP/AVIF at a sensible size (≤ 1200 px wide for banners, ≤ 800 px for products).
5. Keep `Server: ` / `X-Powered-By` minimal, add `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.

## Per-page overrides
- Product: *Meta title* / *Meta description* fields on the product (central catalogue).
- Blog post: *Meta description* field.
- Home page: SEO & Analytics → Search appearance.
- Everything else falls back to the generated title/description.

## Troubleshooting
| Symptom | Fix |
|---|---|
| Search Console "Verification failed" | Code saved? View page source of the home page and search for `google-site-verification`. CDN cache may need purging. |
| "Sitemap could not be fetched" | Open `/sitemap.xml` in a private window; make sure indexing is ON and the URL is the same host you verified. |
| GA4 shows 0 users | Ad-blockers hide your own traffic; test in a clean profile. Check the ID has no spaces and starts with `G-`. |
| Purchases counted twice | You filled both GTM and a pixel that GTM also fires — keep one source. |
| "Duplicate, Google chose different canonical" | Make sure only one hostname serves the store (redirect `www` ↔ non-`www`). |
