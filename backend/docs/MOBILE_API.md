# 3alraf Mobile API (v1) - contract for the Flutter app

Source of truth for the customer app. Machine-readable twin: [`openapi-mobile.yaml`](openapi-mobile.yaml) (OpenAPI 3.1). Regenerate the route-derived skeleton with `php artisan api:docs`; this file and the YAML are curated on top of it. Verified by `tests/Feature/MobileApiTest.php` and `tests/Feature/MobileApiGapsTest.php`.

## 1. Base URL (single store)

One shop, one host: the app uses **one base URL** = `APP_URL` + `/api/v1` (e.g. `https://3alraf.com/api/v1`). There is no store selection.

* `GET /app-config` returns the app switches **and** the shop basics (`store`: name, country, currency code/symbol, default_locale, locales + RTL, timezone, base_url). `GET /store` returns the full shop profile (branding, contact, footer, features).
* **Compatibility with old app builds** (built for the former per-country stores):
  * the `X-Tenant` header is **ignored** (any value, also unknown ones; it never causes a 404);
  * old store hosts `https://{slug}.3alraf.com/api/v1/...` keep answering `/api/*` directly (no redirect); every URL in the responses (images, share links, payment return/webhook URLs) uses `APP_URL`;
  * `GET /tenants` is **deprecated** and returns the one shop as a one-item list in the old picker shape (header `Deprecation: true`); its `slug` = config `mobile.legacy_store_slug` (env `MOBILE_LEGACY_STORE_SLUG`, default `egy`, the slug old builds saved) so they keep their stored session/cart and skip the country picker. `GET /store` returns the same `slug`.
* Endpoints without customer context: `GET /tenants` (deprecated), `GET /app-config`, `GET /health`.

## 2. Request headers

| Header | Required | Meaning |
|---|---|---|
| `X-Tenant` | no (ignored) | sent by old app builds only; the server ignores it |
| `Accept: application/json` | recommended | always send it |
| `Accept-Language` | recommended | `ar` / `en` (first match among the store's `locales`, else the store default). Server content (names, messages, validation text) comes back localised; response header `Content-Language` confirms the choice |
| `Authorization: Bearer <token>` | on protected routes | Sanctum token. Customer tokens carry ability `customer`, affiliate tokens `affiliate`; they are not interchangeable |
| `X-Cart-Token` | guests | guest cart id. The first cart call returns it in the **response header `X-Cart-Token`**; persist it and send it on every call. Cleared on logout |
| `X-Affiliate-Ref` | optional | affiliate uuid (`ref`) of a shared link, for commission attribution |
| `X-Attribution` | optional | growth first-touch: base64url(JSON) of `{utm_source, utm_medium, utm_campaign, utm_content, ref, link, gift_session, ab}`, max 1024 chars, malformed values ignored |

Bodies are JSON (`Content-Type: application/json`) except endpoints with a file (`proof`), which use `multipart/form-data`.

## 3. Response envelope

Every JSON response (success and error) is:

```json
{ "success": true, "message": "Saved successfully.", "data": { }, "meta": { } }
```

* `message` is a localised human string or `null`; `data` is an object, a list, or `null`; `meta` is always an object (pagination, hints such as `next_action`).
* Money values are **decimal strings** in the store currency (`"250.00"`), never numbers. The app never computes totals: display what `cart`, `cart/quote`, `checkout/validate` and the order resources return. Currency code/symbol come from `GET /store`.
* Dates are ISO-8601 with offset.
* Binary endpoints (`orders/{token}/invoice`, `orders/{token}/proof`, `affiliate/products/{slug}/qr`) and `GET /health` are not enveloped.

### Pagination

List endpoints that paginate (`catalog`, `blog`, `orders`, `products/{slug}/reviews`, `custom-pages/{slug}`) accept `page` and `per_page` and return:

```json
"meta": { "pagination": { "current_page": 1, "last_page": 4, "per_page": 24, "total": 90 } }
```

Stop infinite scroll when `current_page >= last_page`.

## 4. Errors

```json
{ "success": false, "message": "The email field is required.", "errors": { "email": ["The email field is required."] }, "data": null, "meta": {} }
```

| HTTP | When | Handling in the app |
|---|---|---|
| 401 | missing / wrong-type / revoked token (`Unauthenticated.`) | clear the session, go to login |
| 403 | forbidden (e.g. return on someone else's order) | show `message` |
| 403 `feature_disabled` | a growth feature is off for the store (`launch`, `affiliate/ambassador`, `gift-finder`). Shape: `{"success":false,"error":"feature_disabled","message":"..."}` | hide the section (see `GET /store` `features`) |
| 404 | unknown route/slug/token or another customer's resource (`Not found.`) | not-found state |
| 422 | validation: `message` = first error, `errors` = per field (map field names to inputs) | show inline |
| 429 | rate limited; `Retry-After` (seconds) header | back off, show `message` |
| 500 | `Something went wrong. Please try again.` | retry / error state |

Failed logins (5) lock the e-mail temporarily and answer 422 even with the right password.

## 5. Rate limits (per client IP, or per user when authenticated)

Default `api`: 120 req/min. Stricter: auth 10/min per route (login, register, google, reset-password, verify, ambassador join); `forgot-password`, verification-notification, account deletion 5/min; `catalog*` 90/min; cart writes 90/min; `cart/quote` 60/min; `checkout/validate` 40/min; `checkout` 20/min; uploads (`proof`, invoice, proof file) 10/min; returns/cancel 10/min; addresses 30/min; devices 30/min; reviews 10/min; payout request 5/min; stock-requests 3/min and 15/hour; `GET /tenants` 60/min; `GET /app-config` & `/health` 120/min. Responses carry `X-RateLimit-Limit` / `X-RateLimit-Remaining`; a 429 adds `Retry-After`.

## 6. Core flows

**Startup:** `GET /app-config?version=<app version>` (switches + `store` basics) -> `GET /store` (branding, locales, currency, features) -> `GET /home`. (Old builds: `GET /tenants` first; see section 1.)

**Guest cart -> login:** add to cart without a token (persist the returned `X-Cart-Token`); on login/register the server adopts the guest cart when `X-Cart-Token` is sent with the request, or call `POST /cart/merge` (with both the bearer token and `X-Cart-Token`) afterwards.

**Checkout:** `GET checkout/cities` -> `GET checkout/shipping?city_id=` -> `GET checkout/packaging` -> `GET checkout/payment-methods` -> `POST checkout/validate` (repeat on each change; `cart/quote` for live totals) -> `POST checkout`. Then act on `meta.next_action`:

* `none` - done (COD / waiting for admin confirmation); show the order.
* `redirect_to_payment` - open `meta.payment_url` in a WebView; when it returns, poll `GET payments/return/{token}` until `meta.paid` is true (`meta.authoritative` is always false: the signed gateway webhook is what marks it paid). A fresh link: `POST orders/{token}/pay`.
* `retry_payment` - the gateway was down; `POST orders/{token}/pay`.
* `upload_proof` - wallet/bank transfer: `POST orders/{token}/proof` (multipart).

Wallet/bank-transfer methods (`requires_proof`) and the prepay leg of COD (when `cod_collect_shipping_upfront`) require the proof file on `checkout` and `checkout/validate`.

**Account deletion:** `DELETE /auth/me` then drop all local state (tokens, cart token, caches).

**Push (store only for now):** `POST /devices` with the FCM token after login / token refresh; `DELETE /devices` on logout. The server stores `customer?, token, platform, locale, app_version` and exposes a `PushDispatcher` interface with a log-only no-op driver (`MOBILE_PUSH_DRIVER=log`). Actual sending (FCM) is a follow-up; no notification inbox endpoint exists.

**Authentication scope:** e-mail + password and Google ID-token only. Phone/OTP login for customers is out of scope (WhatsApp OTP is admin-only).


## Endpoint index

| Method | Path | Auth | Summary |
|---|---|---|---|
| GET | `/tenants` | None | DEPRECATED (old app builds): the one shop as a one-item list in the old picker shape. |
| GET | `/app-config` | None | Force-update and maintenance switches (config/mobile.php + env) + shop basics `store`. |
| GET | `/health` | None | Liveness/readiness probe (database, cache, storage, failed jobs). |
| GET | `/store` | None | Store profile for the UI: |
| GET | `/pages` | None | Static pages (terms, privacy, about...). |
| GET | `/pages/{slug}` | None | One static page. |
| GET | `/custom-pages` | None | Active custom landing pages. |
| GET | `/custom-pages/{slug}` | None | Custom page + its product grid (pinned first). |
| GET | `/home` | Optional customer bearer token | Home screen payload: |
| GET | `/menu` | Optional customer bearer token | Category tree (nested `children`). |
| GET | `/catalog` | Optional customer bearer token | Product listing / search. |
| GET | `/catalog/facets` | Optional customer bearer token | Filter-sheet data for the same scope (accepts the same `category`/`brand`/`tag`/`q` params): |
| GET | `/catalog/suggest` | Optional customer bearer token | Live search suggestions (products + categories). |
| GET | `/categories/{slug}` | Optional customer bearer token | Category page header: |
| GET | `/brands` | Optional customer bearer token | Brands that have at least one sellable product. |
| GET | `/brands/{slug}` | Optional customer bearer token | Brand page header (`products_count` = sellable products). |
| GET | `/tags/{slug}` | Optional customer bearer token | Tag page header. |
| GET | `/products/{slug}` | Optional customer bearer token | Product detail: |
| GET | `/products/{slug}/reviews` | Optional customer bearer token | Approved reviews (paginated). |
| GET | `/bundles` | Optional customer bearer token | Live fixed-price bundles. |
| GET | `/bundles/{slug}` | Optional customer bearer token | One bundle. |
| POST | `/gift-finder` | Optional customer bearer token | Gift finder search (feature `gift_finder`). |
| POST | `/gift-finder/{token}/cart` | Optional customer bearer token | Add the suggested gift set from a search to the cart (feature `gift_finder`). |
| GET | `/blog` | Optional customer bearer token | Published blog posts of the store, newest first. |
| GET | `/blog/{slug}` | Optional customer bearer token | One post; `content` is HTML (sanitised server side; render with a safe HTML widget, never a WebView with JS). |
| POST | `/stock-requests` | Optional customer bearer token | "Notify me when back in stock" (works for guests; a bearer token links the request to the customer). |
| GET | `/launch/{code}` | Optional customer bearer token | Small-batch launch page (interest / survey / limited stock / pre-order). |
| POST | `/launch/{code}` | Optional customer bearer token | Join a launch (idempotent per contact: |
| POST | `/auth/register` | None | Create an account and sign in. |
| POST | `/auth/login` | None | E-mail + password sign in. |
| POST | `/auth/google` | None | Google sign-in with an ID token from the Google SDK (verified server side). |
| POST | `/auth/logout` | Customer bearer token | Revoke the current token. |
| GET | `/auth/me` | Customer bearer token | Current customer. |
| PUT | `/auth/me` | Customer bearer token | Update profile and change password in-app. |
| DELETE | `/auth/me` | Customer bearer token | Delete the account (App Store / Play requirement). |
| POST | `/auth/forgot-password` | None | Send a 6-digit reset code by e-mail. |
| POST | `/auth/reset-password` | None | Reset with the code (single use, expires). |
| POST | `/auth/email/verification-notification` | Customer bearer token | Send the e-mail verification code. |
| POST | `/auth/email/verify` | Customer bearer token | Verify the e-mail with the code. |
| GET | `/cart` | Optional customer bearer token | Current cart (customer cart when signed in, else the guest cart of `X-Cart-Token`). |
| POST | `/cart/items` | Optional customer bearer token | Add a line (`product_id` + `variant_id` (0 = simple product)) or a bundle (`bundle_id`). |
| PUT | `/cart/items/{item}` | Optional customer bearer token | Set the quantity of a line. |
| DELETE | `/cart/items/{item}` | Optional customer bearer token | Remove a line. |
| POST | `/cart/quote` | Optional customer bearer token | Priced totals for the current selections; every field is optional (city, shipping company, payment method, pre |
| POST | `/cart/merge` | Customer bearer token | After login: |
| GET | `/checkout/cities` | Optional customer bearer token | Cities for the address form. |
| GET | `/checkout/shipping` | Optional customer bearer token | Shipping options for a city with the order total per option. |
| GET | `/checkout/packaging` | Optional customer bearer token | Special-packaging options (price only). |
| GET | `/checkout/payment-methods` | Optional customer bearer token | Usable payment methods. |
| POST | `/checkout/validate` | Optional customer bearer token | Full quote with the strict checkout rules (stock, discount, shipping, payment, proof). |
| POST | `/checkout` | Optional customer bearer token | Place the order from the cart. |
| GET | `/orders` | Customer bearer token | The customer's orders, newest first (no items). |
| GET | `/orders/{token}` | Optional customer bearer token | Order detail by its unguessable public token (guest-safe): |
| GET | `/orders/{token}/track` | Optional customer bearer token | Compact tracking: |
| GET | `/orders/{token}/invoice` | Optional customer bearer token | Invoice as a PDF download (`Content-Type: |
| GET | `/orders/{token}/proof` | Optional customer bearer token | The uploaded transfer proof (image/PDF stream from the private disk, `Cache-Control: |
| POST | `/orders/{token}/proof` | Optional customer bearer token | Upload or replace the wallet-transfer proof (multipart field `proof`: |
| POST | `/orders/{token}/pay` | Optional customer bearer token | New gateway link for an unpaid online order: |
| GET | `/payments/return/{token}` | Optional customer bearer token | Call when the in-app browser returns from the gateway. |
| POST | `/orders/{token}/cancel` | Customer bearer token | Cancel with a structured reason (feature `return_intel`; 404 when off). |
| POST | `/orders/{token}/return` | Customer bearer token | Request a return for a delivered order (owner only; 403 otherwise, 422 when not delivered or quantities exceed |
| GET | `/addresses` | Customer bearer token | Saved addresses (default first). |
| POST | `/addresses` | Customer bearer token | Create an address (the first one becomes default). |
| PUT | `/addresses/{id}` | Customer bearer token | Update an address (another customer's id = 404). |
| DELETE | `/addresses/{id}` | Customer bearer token | Delete an address. |
| GET | `/wishlist` | Customer bearer token | Wishlist products. |
| POST | `/wishlist/{product}/toggle` | Customer bearer token | Add/remove a product (`{product}` = numeric product id). |
| POST | `/products/{slug}/reviews` | Customer bearer token | Write a review (delivered purchase only, one per product; goes to moderation). |
| POST | `/devices` | Optional customer bearer token | Register/refresh a push token (FCM). |
| DELETE | `/devices` | Optional customer bearer token | Unregister a push token (call on logout / opt-out). |
| POST | `/affiliate/register` | None | Apply as an affiliate. |
| POST | `/affiliate/login` | None | Sign in (approved affiliates only). |
| POST | `/affiliate/logout` | Affiliate bearer token | Revoke the token. |
| GET | `/affiliate/me` | Affiliate bearer token | Profile; `ref` is the uuid used in share links / `X-Affiliate-Ref`. |
| GET | `/affiliate/dashboard` | Affiliate bearer token | Stats, tier, commissions, payouts. |
| GET | `/affiliate/products` | Affiliate bearer token | Sellable products with the affiliate share `link` and `qr_url`. |
| GET | `/affiliate/products/{slug}/qr` | Affiliate bearer token | PNG QR of the share link (binary). |
| GET | `/affiliate/payouts` | Affiliate bearer token | Payout history. |
| POST | `/affiliate/payouts` | Affiliate bearer token | Request a payout of the approved balance. |
| GET | `/affiliate/ambassador` | Affiliate bearer token | Ambassador portal (feature `ambassadors`, else 403): |
| POST | `/affiliate/ambassador/join` | Affiliate bearer token | Opt in as an ambassador (disclosure + terms). |
| POST | `/affiliate/ambassador/links` | Affiliate bearer token | Create a per-channel tracked link for a product (404 when not enrolled). |


---

## Bootstrap

### `GET /tenants`

**Auth:** None  
**Host:** `APP_URL/api/v1`

**DEPRECATED** - kept only so old app builds (country picker) keep working. Always a one-item list (the shop, values from Settings > General); response header `Deprecation: true`. Fields = exactly what old builds parse (`Tenant.fromJson`): `slug` (= `mobile.legacy_store_slug`, default `egy`), `name`, `country`, `flag`, `currency` (code), `default_locale`, `locales`, `languages` (native names of `locales`), `logo_url` (falls back to the 3alraf logo), `base_url` (= `APP_URL`, used by old builds for share links). New builds use `GET /app-config` / `GET /store`.

**Notes:** Rate limit 60/min/IP.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "slug": "egy",
      "name": "3alraf Egypt",
      "country": "EG",
      "flag": "🇪🇬",
      "currency": "EGP",
      "default_locale": "ar",
      "locales": [
        "ar",
        "en"
      ],
      "languages": [
        "العربية",
        "English"
      ],
      "logo_url": "http://3alraf.test/logo.png",
      "base_url": "http://3alraf.test"
    }
  ],
  "meta": {}
}
```

### `GET /app-config`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Force-update and maintenance switches (config/mobile.php + env) and the shop basics `store` (from Settings > General: `name`, `country`, `currency {code, symbol}`, `default_locale`, `locales [{code, rtl}]`, `timezone`, `base_url` = `APP_URL`). `Accept-Language` selects the maintenance message (`ar`/`en`).

**Query / params:** `version` (optional): the app version, e.g. `1.2.0`. When given, `update_required` (version < min_version) and `update_available` (version < latest_version) are filled, otherwise `null`.

**Notes:** `min_version` / `latest_version` are `x.y.z`. Env: `MOBILE_MIN_VERSION`, `MOBILE_LATEST_VERSION`, `MOBILE_STORE_URL_IOS|ANDROID`, `MOBILE_MAINTENANCE`, `MOBILE_MAINTENANCE_MESSAGE_AR|EN`. Rate limit 120/min/IP. Never blocked by maintenance itself.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "meta": {},
  "data": {
    "min_version": "1.0.0",
    "latest_version": "1.0.0",
    "store_urls": {
      "ios": null,
      "android": null
    },
    "maintenance": false,
    "maintenance_message": null,
    "update_required": true,
    "update_available": true,
    "store": {
      "name": "3alraf Egypt",
      "country": "EG",
      "currency": {
        "code": "EGP",
        "symbol": "EGP"
      },
      "default_locale": "ar",
      "locales": [
        {
          "code": "ar",
          "rtl": true
        },
        {
          "code": "en",
          "rtl": false
        }
      ],
      "timezone": "Africa/Cairo",
      "base_url": "http://3alraf.test"
    }
  }
}
```

### `GET /health`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Liveness/readiness probe (database, cache, storage, failed jobs). Not enveloped.

**Example response (200):**

```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "cache": "ok",
    "storage": "ok",
    "failed_jobs": "ok"
  },
  "time": "2026-11-01T21:51:44+00:00"
}
```


---

## Store

### `GET /store`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Store profile for the UI: currency, locales (+RTL flag), branding (logo, favicon, colours), contact (email/phone/WhatsApp digits for `wa.me/<digits>`), social links, app-store links, footer data (static pages, whether a blog exists, accepted payment methods) and feature switches for optional sections.

**Notes:** `slug` = `mobile.legacy_store_slug` (compat only; there is one shop). `colors` = shop overrides (setting `branding.colors`, hex only) merged over the 3alraf defaults (config/mobile.php), so every key is always present. `social` only contains configured networks. `contact.whatsapp` = `settings.contact.whatsapp` else the organisation phone, digits only. `features.*` hide Gift finder / Small-batch launch / Ambassadors entry points when `false` (the matching endpoints answer 403 `feature_disabled`).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "slug": "egy",
    "name": "3alraf Egypt",
    "country": "EG",
    "currency": {
      "code": "EGP",
      "symbol": "EGP"
    },
    "timezone": "Africa/Cairo",
    "default_locale": "ar",
    "locales": [
      {
        "code": "ar",
        "rtl": true
      }
    ],
    "cod_collect_shipping_upfront": false,
    "prices_include_tax": false,
    "logo_url": "http://3alraf.test/logo.png",
    "favicon_url": "http://3alraf.test/logo-192.png",
    "colors": {
      "primary": "#A8456C",
      "primary_dark": "#8F3A5C",
      "primary_soft": "#C96F91",
      "primary_tint": "#FBEAF0",
      "accent": "#D7B987",
      "accent_tint": "#F4E8D5",
      "text": "#45313B",
      "heading": "#2F2129",
      "muted": "#7A6672",
      "border": "#F0DDE4",
      "surface": "#FFF9F7",
      "card": "#FFFFFF",
      "success": "#2F806A",
      "warning": "#D99A36",
      "danger": "#C8384F"
    },
    "contact": {
      "email": "hello@3alraf.com",
      "phone": "+20 100 123 4567",
      "whatsapp": "201001234567"
    },
    "social": {
      "facebook": "https://facebook.com/3alraf",
      "instagram": "https://instagram.com/3alraf"
    },
    "app_links": {
      "ios": "https://apps.apple.com/app/id000",
      "android": "https://play.google.com/store/apps/details?id=com.alraf.shop"
    },
    "footer": {
      "pages": [
        {
          "slug": "privacy-policy",
          "title": "Privacy policy"
        }
      ],
      "has_blog": true,
      "payment_methods": [
        {
          "name": "Cash on delivery",
          "type": "cod",
          "provider": "cod"
        }
      ]
    },
    "features": {
      "gift_finder": true,
      "small_batch_lab": true,
      "ambassadors": true
    }
  },
  "meta": {}
}
```

### `GET /pages`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Static pages (terms, privacy, about...).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "slug": "privacy-policy",
      "title": "Privacy policy"
    }
  ],
  "meta": {}
}
```

### `GET /pages/{slug}`

**Auth:** None  
**Host:** `APP_URL/api/v1`

One static page. `content` is plain text / light HTML.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "slug": "terms",
    "title": "Terms & conditions",
    "content": "Content of Terms & conditions"
  },
  "meta": {}
}
```

### `GET /custom-pages`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Active custom landing pages.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "slug": "offers",
      "title": "Offers",
      "url": "http://3alraf.test/offers"
    }
  ],
  "meta": {}
}
```

### `GET /custom-pages/{slug}`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Custom page + its product grid (pinned first).

**Query / params:** `price_min`, `price_max`, `sort` (`newest|price_asc|price_desc|best_selling`), `page`


---

## Catalog

### `GET /home`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Home screen payload: banners (hero/strips/tiles/popup), category rail, flash sale, product rails, brands.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "banners": {
      "hero": [],
      "strips": [],
      "tiles": [],
      "popup": null
    },
    "categories": [
      {
        "id": 1,
        "name": "Devices",
        "slug": "devices",
        "image": null,
        "icon": null,
        "children": []
      }
    ],
    "flash_sale": null,
    "rails": {
      "recommended": [
        {
          "id": 2,
          "slug": "mid",
          "name": "P mid",
          "image": null,
          "price": "250.00",
          "old_price": "300.00",
          "discount_percent": 17,
          "flash": false,
          "flash_ends_at": null,
          "rating": null,
          "reviews_count": 0
        }
      ],
      "most_sold": [],
      "most_reviewed": []
    },
    "brands": [
      {
        "id": 1,
        "name": "Sony",
        "slug": "sony",
        "logo": null
      }
    ]
  },
  "meta": {}
}
```

### `GET /menu`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Category tree (nested `children`).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "name": "Devices",
      "slug": "devices",
      "image": null,
      "icon": null,
      "children": []
    }
  ],
  "meta": {}
}
```

### `GET /catalog`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Product listing / search. One endpoint for category, brand, tag and search pages (combine freely).

**Query / params:** `category` (slug), `brand` (slug), `tag` (slug), `q` (min 2 chars), `brands[]` (ids), `values[]` (attribute value ids), `tags[]` (ids), `price_min`, `price_max`, `sort` (`newest|price_asc|price_desc|best_selling|top_rated`), `per_page` (1-60, default 24), `page`. Unknown/inactive `category`, `brand` or `tag` slug = 404.

**Notes:** Pagination in `meta.pagination`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2,
      "slug": "mid",
      "name": "P mid",
      "image": null,
      "price": "250.00",
      "old_price": "300.00",
      "discount_percent": 17,
      "flash": false,
      "flash_ends_at": null,
      "rating": null,
      "reviews_count": 0
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 1,
      "total": 2
    }
  }
}
```

### `GET /catalog/facets`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Filter-sheet data for the same scope (accepts the same `category`/`brand`/`tag`/`q` params): brands, tags, attributes with values, price range, sort keys.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "brands": [
      {
        "id": 1,
        "name": "Sony",
        "slug": "sony",
        "logo": null
      }
    ],
    "attributes": [],
    "price": {
      "min": 100,
      "max": 250
    },
    "sorts": [
      "newest"
    ]
  },
  "meta": {}
}
```

### `GET /catalog/suggest`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Live search suggestions (products + categories). `q` shorter than 2 chars returns empty lists.

**Query / params:** `q`

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "products": [
      {
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "price": "100.00"
      }
    ],
    "categories": []
  },
  "meta": {}
}
```

### `GET /categories/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Category page header: name, image, icon, description, breadcrumbs (root to self), active children. Its products: `GET /catalog?category={slug}`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Devices",
    "slug": "devices",
    "image": null,
    "icon": null,
    "description": null,
    "parent_id": null,
    "breadcrumbs": [
      {
        "id": 1,
        "name": "Devices",
        "slug": "devices"
      }
    ],
    "children": []
  },
  "meta": {}
}
```

### `GET /brands`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Brands that have at least one sellable product.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "name": "Sony",
      "slug": "sony",
      "logo": null
    }
  ],
  "meta": {}
}
```

### `GET /brands/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Brand page header (`products_count` = sellable products). Its products: `GET /catalog?brand={slug}`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Sony",
    "slug": "sony",
    "logo": null,
    "products_count": 2
  },
  "meta": {}
}
```

### `GET /tags/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Tag page header. Its products: `GET /catalog?tag={slug}`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "New",
    "slug": "new"
  },
  "meta": {}
}
```

### `GET /products/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Product detail: gallery, variants with exact prices/stock, options, rating, reviews, related, `in_wishlist`, `can_review`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 2,
    "slug": "mid",
    "name": "P mid",
    "description": null,
    "sku": "MID",
    "barcode": "2006938135225",
    "type": "simple",
    "brand": {
      "id": 1,
      "name": "Sony",
      "logo": null
    },
    "categories": [
      {
        "id": 1,
        "name": "Devices",
        "slug": "devices"
      }
    ],
    "images": [],
    "price": "250.00",
    "regular_price": "300.00",
    "options": [],
    "variants": [
      {
        "id": 0,
        "values": [],
        "price": "250.00",
        "old": "300.00",
        "flash_ends": null,
        "stock": 50,
        "in_stock": true,
        "label": null,
        "alert": 5,
        "barcode": "2006938135225",
        "sku": "MID",
        "image": null
      }
    ],
    "rating": {
      "average": 0,
      "count": 0
    },
    "reviews": [],
    "related": [
      {
        "id": 1,
        "slug": "cheap",
        "name": "P cheap",
        "image": null,
        "price": "100.00",
        "old_price": null,
        "discount_percent": null,
        "flash": false,
        "flash_ends_at": null,
        "rating": null,
        "reviews_count": 0
      }
    ],
    "in_wishlist": false,
    "can_review": false
  },
  "meta": {}
}
```

### `GET /products/{slug}/reviews`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Approved reviews (paginated).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 0
    }
  }
}
```

### `GET /bundles`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Live fixed-price bundles. Add one with `POST /cart/items {bundle_id, quantity}`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {}
}
```

### `GET /bundles/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

One bundle.

### `POST /gift-finder`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Gift finder search (feature `gift_finder`).

**Body (`*` = required):** `budget`* (number), `occasion`, `recipient`, `category_id`, `restrictions`

### `POST /gift-finder/{token}/cart`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Add the suggested gift set from a search to the cart (feature `gift_finder`).


---

## Content

### `GET /blog`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Published blog posts of the store, newest first. List items carry no `content`.

**Query / params:** `per_page` (1-30, default 9), `page`

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "slug": "hello-world",
      "title": "Hello world",
      "excerpt": "Summary",
      "meta_description": null,
      "published_at": "2026-10-31T21:51:44+02:00",
      "url": "http://3alraf.test/en/blog/hello-world"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 9,
      "total": 1
    }
  }
}
```

### `GET /blog/{slug}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

One post; `content` is HTML (sanitised server side; render with a safe HTML widget, never a WebView with JS).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "slug": "hello-world",
    "title": "Hello world",
    "excerpt": "Summary",
    "meta_description": null,
    "published_at": "2026-10-31T21:51:44+02:00",
    "url": "http://3alraf.test/en/blog/hello-world",
    "content": "<p>Body</p>"
  },
  "meta": {}
}
```

### `POST /stock-requests`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

"Notify me when back in stock" (works for guests; a bearer token links the request to the customer). Same rules and de-duplication as the web form: the same phone asking twice for the same item while the first is open is a no-op (still 201).

**Body (`*` = required):** `product_id`* (sellable product), `variant_id`, `name`* (max 120), `phone`* (no country code; the store code is added), `phone_country_code`, `note` (max 500)

**Notes:** Rate limit: 3/min and 15/hour per IP.

**Example response (201):**

```json
{
  "success": true,
  "message": "Your request was sent. We will contact you soon.",
  "data": null,
  "meta": {}
}
```

### `GET /launch/{code}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Small-batch launch page (interest / survey / limited stock / pre-order). Feature `small_batch_lab`. `code` = 6-12 lowercase alphanumerics; draft or other-store tests = 404.

**Notes:** When `preorder` is true, show `preorder_terms` and send `preorder_accepted=true`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": "abcd1234",
    "name": "Rose serum pilot",
    "description": "Help us decide",
    "mode": "survey",
    "survey_question": "Which size would you buy?",
    "preorder": false,
    "preorder_terms": null,
    "is_open": true,
    "ends_on": null,
    "product": {
      "id": 1,
      "slug": "cheap",
      "name": "P cheap",
      "image": null
    }
  },
  "meta": {}
}
```

### `POST /launch/{code}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Join a launch (idempotent per contact: a repeat returns `created:false`, HTTP 200). 422 when the test is closed, sold out, consent/terms missing.

**Body (`*` = required):** `contact`* (phone or e-mail, max 120), `answer` (survey, max 300), `quantity` (1-20), `preorder_accepted`, `consent`* (must be true)

**Example response (201):**

```json
{
  "success": true,
  "message": "Thank you, your interest is recorded.",
  "data": {
    "created": true
  },
  "meta": {}
}
```


---

## Auth (customer)

### `POST /auth/register`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Create an account and sign in. Sends an e-mail verification code. A guest cart sent in `X-Cart-Token` is adopted.

**Body (`*` = required):** `name`*, `email`*, `password`* (min 8), `password_confirmation`*, `phone`, `phone_country_code`, `device_name`

**Notes:** Rate limit 10/min. The same e-mail may exist in another store.

**Example response (201):**

```json
{
  "success": true,
  "message": "Welcome back!",
  "data": {
    "token": "12|AbCdEf...",
    "token_type": "Bearer",
    "abilities": [
      "customer"
    ],
    "customer": {
      "id": 2,
      "name": "Mona",
      "email": "mona@example.com",
      "phone": null,
      "phone_country_code": null,
      "phone_full": null,
      "email_verified": false,
      "created_at": "2026-11-01T23:51:45+02:00"
    }
  },
  "meta": {}
}
```

### `POST /auth/login`

**Auth:** None  
**Host:** `APP_URL/api/v1`

E-mail + password sign in. 5 failures lock the e-mail for a while (422). Adopts the guest cart.

**Body (`*` = required):** `email`*, `password`*, `device_name`

**Notes:** Rate limit 10/min.

**Example response (200):**

```json
{
  "success": true,
  "message": "Welcome back!",
  "data": {
    "token": "12|AbCdEf...",
    "token_type": "Bearer",
    "abilities": [
      "customer"
    ],
    "customer": {
      "id": 1,
      "name": "Demo Customer",
      "email": "customer@egy.test",
      "phone": "01000000001",
      "phone_country_code": null,
      "phone_full": "01000000001",
      "email_verified": false,
      "created_at": "2026-11-01T21:51:44+02:00"
    }
  },
  "meta": {}
}
```

### `POST /auth/google`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Google sign-in with an ID token from the Google SDK (verified server side).

**Body (`*` = required):** `id_token`*, `device_name`

### `POST /auth/logout`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Revoke the current token.

**Example response (200):**

```json
{
  "success": true,
  "message": "Logged out.",
  "data": null,
  "meta": {}
}
```

### `GET /auth/me`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Current customer.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Demo Customer",
    "email": "customer@egy.test",
    "phone": "01000000001",
    "phone_country_code": null,
    "phone_full": "01000000001",
    "email_verified": false,
    "created_at": "2026-11-01T21:51:44+02:00"
  },
  "meta": {}
}
```

### `PUT /auth/me`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Update profile and change password in-app. `name` and `email` are always required. To change the password send `password` + `password_confirmation` + `current_password`; changing the e-mail also needs `current_password` (and resets verification). All other tokens of the customer are revoked on a credential change; the calling token stays valid.

**Body (`*` = required):** `name`*, `email`*, `phone`, `phone_country_code`, `password`, `password_confirmation`, `current_password` (required when changing e-mail/password)

**Example response (200):**

```json
{
  "success": true,
  "message": "Saved successfully.",
  "data": {
    "id": 1,
    "name": "Cust",
    "email": "customer@egy.test",
    "phone": "01100000111",
    "phone_country_code": "+20",
    "phone_full": "+201100000111",
    "email_verified": false,
    "created_at": "2026-11-01T21:51:44+02:00"
  },
  "meta": {}
}
```

### `DELETE /auth/me`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Delete the account (App Store / Play requirement). Irreversible: personal data is anonymised, addresses/wishlist/push tokens removed, all tokens revoked, the e-mail becomes free to register again. Orders are kept (anonymous customer) for accounting.

**Body (`*` = required):** `password`* (the account password) - or `confirm: true` for Google-only accounts without a password

**Notes:** Rate limit 5/min. Wrong password = 422 on `password`.

**Example response (200):**

```json
{
  "success": true,
  "message": "Your account has been deleted.",
  "data": null,
  "meta": {}
}
```

### `POST /auth/forgot-password`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Send a 6-digit reset code by e-mail. Always 200 (does not reveal whether the e-mail exists).

**Body (`*` = required):** `email`*

**Notes:** Rate limit 5/min.

**Example response (200):**

```json
{
  "success": true,
  "message": "If the e-mail exists, a reset code has been sent.",
  "data": null,
  "meta": {}
}
```

### `POST /auth/reset-password`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Reset with the code (single use, expires). Revokes every existing session.

**Body (`*` = required):** `email`*, `code`* (6 digits), `password`*, `password_confirmation`*

### `POST /auth/email/verification-notification`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Send the e-mail verification code. 422 when already verified.

**Notes:** Rate limit 5/min.

**Example response (200):**

```json
{
  "success": true,
  "message": "A verification code has been sent to your e-mail.",
  "data": null,
  "meta": {}
}
```

### `POST /auth/email/verify`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Verify the e-mail with the code. Returns the customer.

**Body (`*` = required):** `code`*


---

## Cart

### `GET /cart`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Current cart (customer cart when signed in, else the guest cart of `X-Cart-Token`). Totals are computed by the server; display them, never recompute.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 2,
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "unit_price": "100.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "200.00",
      "discount": "0.00",
      "shipping": "0.00",
      "shipping_discount": "0.00",
      "shipping_net": "0.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "200.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "0.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```

### `POST /cart/items`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Add a line (`product_id` + `variant_id` (0 = simple product)) or a bundle (`bundle_id`). Quantities add up. Returns the cart. The first guest call returns the `X-Cart-Token` response header: persist it and send it on every call.

**Body (`*` = required):** `product_id` or `bundle_id`, `variant_id`, `quantity`*

**Notes:** 422 on insufficient stock. Rate limit 90/min.

**Example response (200):**

```json
{
  "success": true,
  "message": "Added to cart.",
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 2,
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "unit_price": "100.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "200.00",
      "discount": "0.00",
      "shipping": "0.00",
      "shipping_discount": "0.00",
      "shipping_net": "0.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "200.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "0.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```

### `PUT /cart/items/{item}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Set the quantity of a line.

**Body (`*` = required):** `quantity`*

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 3,
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "unit_price": "100.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "300.00",
      "discount": "0.00",
      "shipping": "0.00",
      "shipping_discount": "0.00",
      "shipping_net": "0.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "300.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "0.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```

### `DELETE /cart/items/{item}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Remove a line. Returns the cart.

### `POST /cart/quote`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Priced totals for the current selections; every field is optional (city, shipping company, payment method, prepay method, `discount_code`, `packaging_option_id`). Use it for the cart screen and each checkout step.

**Body (`*` = required):** Same fields as `POST /checkout` (none required)

**Notes:** Rate limit 60/min. An invalid discount code = 422 on `discount_code`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 3,
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "unit_price": "100.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "300.00",
      "discount": "0.00",
      "shipping": "40.00",
      "shipping_discount": "0.00",
      "shipping_net": "40.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "340.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "340.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```

### `POST /cart/merge`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

After login: merge the guest cart (`X-Cart-Token`) into the customer cart (quantities add up) and consume the guest cart.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 2,
        "product_id": 2,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 1,
        "name": "P mid",
        "slug": "mid",
        "image": null,
        "unit_price": "250.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "250.00",
      "discount": "0.00",
      "shipping": "0.00",
      "shipping_discount": "0.00",
      "shipping_net": "0.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "250.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "0.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```


---

## Checkout

### `GET /checkout/cities`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Cities for the address form.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "name": "Cairo"
    }
  ],
  "meta": {}
}
```

### `GET /checkout/shipping`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Shipping options for a city with the order total per option.

**Query / params:** `city_id`*

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "shipping_company_id": 1,
      "name": "Fast Shipping",
      "eta_days": "1-2",
      "supports_cod": true,
      "fee": "40.00",
      "free": false,
      "order_total": "340.00"
    }
  ],
  "meta": {}
}
```

### `GET /checkout/packaging`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Special-packaging options (price only).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {}
}
```

### `GET /checkout/payment-methods`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Usable payment methods. `meta.cod_collect_shipping_upfront=true` means COD needs a prepaid shipping leg (`prepay_method_id` + proof).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "type": "cod",
      "provider": "cod",
      "name": "Cash on delivery",
      "instructions": null,
      "account_number": null,
      "requires_proof": false,
      "fee_percent": "0.000",
      "fee_fixed": "0.00"
    }
  ],
  "meta": {
    "cod_collect_shipping_upfront": false
  }
}
```

### `POST /checkout/validate`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Full quote with the strict checkout rules (stock, discount, shipping, payment, proof). 200 = placeable; 422 with field `errors` otherwise. Send multipart when a `proof` file is included.

**Body (`*` = required):** Same as `POST /checkout`

**Notes:** Rate limit 40/min.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "is_bundle": false,
        "components": [],
        "quantity": 3,
        "name": "P cheap",
        "slug": "cheap",
        "image": null,
        "unit_price": "100.00",
        "available": true
      }
    ],
    "packaging": null,
    "has_flash": false,
    "totals": {
      "subtotal": "300.00",
      "discount": "0.00",
      "shipping": "40.00",
      "shipping_discount": "0.00",
      "shipping_net": "40.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "340.00",
      "cod_fee": "0.00",
      "cod_fee_label": "COD fee",
      "packaging_fee": "0.00",
      "cod_amount": "340.00",
      "prepaid_shipping": "0.00"
    }
  },
  "meta": {}
}
```

### `POST /checkout`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Place the order from the cart. Guests need `name`, `phone`, `city_id`, `address`; signed-in customers may send `address_id` instead. The order always waits for admin confirmation. Follow `meta.next_action`: `none` (COD), `redirect_to_payment` (open `meta.payment_url` in a WebView, then poll `GET /payments/return/{token}`), `retry_payment` (`POST /orders/{token}/pay`), `upload_proof`.

**Body (`*` = required):** `address_id` (customer address) or `name`*, `phone`*, `phone_country_code`, `email`, `city_id`*, `address`*, `notes`; `shipping_company_id`*, `payment_method_id`*, `prepay_method_id` (COD with upfront shipping), `discount_code`, `packaging_option_id`, `proof` (file: jpg/png/webp/pdf, required for wallet/bank-transfer methods; multipart)

**Notes:** Rate limit 20/min. 429 + `Retry-After` when the proof anti-abuse limiter trips.

**Example response (201):**

```json
{
  "success": true,
  "message": "Your order has been placed.",
  "data": {
    "number": "ORD-000001",
    "token": "2e81f703-717c-4d21-9762-f47f823bff9f",
    "status": {
      "value": "pending",
      "label": "Pending"
    },
    "payment_status": "unpaid",
    "proof_status": null,
    "payment_method": {
      "id": 1,
      "type": "cod",
      "name": "Cash on delivery"
    },
    "subtotal": "300.00",
    "discount": "0.00",
    "shipping": "40.00",
    "tax": "0.00",
    "payment_fee": "0.00",
    "total": "340.00",
    "paid_amount": "0.00",
    "due": "340.00",
    "cod_fee": "0.00",
    "cod_fee_label": null,
    "cod_amount": "340.00",
    "prepaid_shipping": "0.00",
    "currency": "EGP",
    "packaging_fee": "0.00",
    "packaging_name": null,
    "placed_at": "2026-11-01T23:51:45+02:00",
    "items_count": 3,
    "customer": {
      "name": "Ali",
      "phone": "01000000100",
      "phone_country_code": "+20",
      "phone_full": "+201000000100",
      "email": "g@x.test"
    },
    "city": "Cairo",
    "address": "St 1",
    "notes": null,
    "shipping_company": "Fast Shipping",
    "prepay_method": null,
    "has_proof": false,
    "pay_now": "340.00",
    "can_pay_online": false,
    "can_upload_proof": false,
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "bundle_name": null,
        "bundle_group": null,
        "name": "P cheap",
        "sku": "CHEAP",
        "unit_price": "100.00",
        "quantity": 3,
        "line_subtotal": "300.00",
        "line_discount": "0.00",
        "returned_qty": 0
      }
    ],
    "timeline": [
      {
        "status": "pending",
        "note": null,
        "at": "2026-11-01T23:51:45+02:00"
      }
    ],
    "returns": []
  },
  "meta": {
    "next_action": "none",
    "payment_url": null
  }
}
```


---

## Orders

### `GET /orders`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

The customer's orders, newest first (no items).

**Query / params:** `per_page` (1-50, default 10), `page`

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "number": "ORD-000002",
      "token": "184aa88f-78b2-4f83-92aa-86bc8b9df0b8",
      "status": {
        "value": "pending",
        "label": "Pending"
      },
      "payment_status": "unpaid",
      "proof_status": null,
      "payment_method": {
        "id": 1,
        "type": "cod",
        "name": "Cash on delivery"
      },
      "subtotal": "200.00",
      "discount": "0.00",
      "shipping": "40.00",
      "tax": "0.00",
      "payment_fee": "0.00",
      "total": "240.00",
      "paid_amount": "0.00",
      "due": "240.00",
      "cod_fee": "0.00",
      "cod_fee_label": null,
      "cod_amount": "240.00",
      "prepaid_shipping": "0.00",
      "currency": "EGP",
      "packaging_fee": "0.00",
      "packaging_name": null,
      "placed_at": "2026-11-01T23:51:45+02:00",
      "items_count": 2
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    }
  }
}
```

### `GET /orders/{token}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Order detail by its unguessable public token (guest-safe): items, customer, timeline, returns, payment state, `can_pay_online`, `can_upload_proof`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "number": "ORD-000001",
    "token": "2e81f703-717c-4d21-9762-f47f823bff9f",
    "status": {
      "value": "pending",
      "label": "Pending"
    },
    "payment_status": "unpaid",
    "proof_status": null,
    "payment_method": {
      "id": 1,
      "type": "cod",
      "name": "Cash on delivery"
    },
    "subtotal": "300.00",
    "discount": "0.00",
    "shipping": "40.00",
    "tax": "0.00",
    "payment_fee": "0.00",
    "total": "340.00",
    "paid_amount": "0.00",
    "due": "340.00",
    "cod_fee": "0.00",
    "cod_fee_label": null,
    "cod_amount": "340.00",
    "prepaid_shipping": "0.00",
    "currency": "EGP",
    "packaging_fee": "0.00",
    "packaging_name": null,
    "placed_at": "2026-11-01T23:51:45+02:00",
    "items_count": 3,
    "customer": {
      "name": "Ali",
      "phone": "01000000100",
      "phone_country_code": "+20",
      "phone_full": "+201000000100",
      "email": "g@x.test"
    },
    "city": "Cairo",
    "address": "St 1",
    "notes": null,
    "shipping_company": "Fast Shipping",
    "prepay_method": null,
    "has_proof": false,
    "pay_now": "340.00",
    "can_pay_online": false,
    "can_upload_proof": false,
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "bundle_name": null,
        "bundle_group": null,
        "name": "P cheap",
        "sku": "CHEAP",
        "unit_price": "100.00",
        "quantity": 3,
        "line_subtotal": "300.00",
        "line_discount": "0.00",
        "returned_qty": 0
      }
    ],
    "timeline": [
      {
        "status": "pending",
        "note": null,
        "at": "2026-11-01T23:51:45+02:00"
      }
    ],
    "returns": []
  },
  "meta": {}
}
```

### `GET /orders/{token}/track`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Compact tracking: number, status, payment status, timeline.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "number": "ORD-000001",
    "status": "pending",
    "payment_status": "unpaid",
    "timeline": [
      {
        "status": "pending",
        "note": null,
        "at": "2026-11-01T23:51:45+02:00"
      }
    ]
  },
  "meta": {}
}
```

### `GET /orders/{token}/invoice`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Invoice as a PDF download (`Content-Type: application/pdf`, `Content-Disposition: attachment`). Rendered in the request locale. Binary response, not enveloped; 404 for an unknown token or another store's order.

**Notes:** Rate limit 10/min. Save the bytes to a temp file and open/share it.

### `GET /orders/{token}/proof`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

The uploaded transfer proof (image/PDF stream from the private disk, `Cache-Control: private, no-store`). 404 when nothing was uploaded.

**Notes:** Binary response, not enveloped. Rate limit 10/min.

### `POST /orders/{token}/proof`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Upload or replace the wallet-transfer proof (multipart field `proof`: jpg, png, webp, pdf). Returns the order. The admin approval is what confirms payment.

**Body (`*` = required):** `proof`* (file)

**Notes:** Rate limit 10/min plus a re-upload limiter (429 + `Retry-After`).

### `POST /orders/{token}/pay`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

New gateway link for an unpaid online order: `data.payment_url`.

### `GET /payments/return/{token}`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Call when the in-app browser returns from the gateway. NOT authoritative: only the signed webhook marks an order paid, so poll until `meta.paid` is true.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "number": "ORD-000001",
    "token": "2e81f703-717c-4d21-9762-f47f823bff9f",
    "status": {
      "value": "pending",
      "label": "Pending"
    },
    "payment_status": "unpaid",
    "proof_status": null,
    "payment_method": {
      "id": 1,
      "type": "cod",
      "name": "Cash on delivery"
    },
    "subtotal": "300.00",
    "discount": "0.00",
    "shipping": "40.00",
    "tax": "0.00",
    "payment_fee": "0.00",
    "total": "340.00",
    "paid_amount": "0.00",
    "due": "340.00",
    "cod_fee": "0.00",
    "cod_fee_label": null,
    "cod_amount": "340.00",
    "prepaid_shipping": "0.00",
    "currency": "EGP",
    "packaging_fee": "0.00",
    "packaging_name": null,
    "placed_at": "2026-11-01T23:51:45+02:00",
    "items_count": 3,
    "customer": {
      "name": "Ali",
      "phone": "01000000100",
      "phone_country_code": "+20",
      "phone_full": "+201000000100",
      "email": "g@x.test"
    },
    "city": "Cairo",
    "address": "St 1",
    "notes": null,
    "shipping_company": "Fast Shipping",
    "prepay_method": null,
    "has_proof": false,
    "pay_now": "340.00",
    "can_pay_online": false,
    "can_upload_proof": false,
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "variant_id": 0,
        "bundle_id": null,
        "bundle_name": null,
        "bundle_group": null,
        "name": "P cheap",
        "sku": "CHEAP",
        "unit_price": "100.00",
        "quantity": 3,
        "line_subtotal": "300.00",
        "line_discount": "0.00",
        "returned_qty": 0
      }
    ],
    "timeline": [
      {
        "status": "pending",
        "note": null,
        "at": "2026-11-01T23:51:45+02:00"
      }
    ],
    "returns": []
  },
  "meta": {
    "paid": false,
    "authoritative": false
  }
}
```

### `POST /orders/{token}/cancel`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Cancel with a structured reason (feature `return_intel`; 404 when off).

**Body (`*` = required):** `reason_code`

### `POST /orders/{token}/return`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Request a return for a delivered order (owner only; 403 otherwise, 422 when not delivered or quantities exceed what was bought).

**Body (`*` = required):** `items`* `[{order_item_id, quantity}]`, `reason`

**Example response (201):**

```json
{
  "success": true,
  "message": "Return created.",
  "data": {
    "number": "RET-000001",
    "status": "requested",
    "reason": "broken",
    "refund_amount": "",
    "requested_at": "2026-11-01T23:51:45+02:00"
  },
  "meta": {}
}
```


---

## Account

### `GET /addresses`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Saved addresses (default first).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "label": "Home",
      "name": "Ali",
      "phone": "01000000100",
      "phone_country_code": "+20",
      "phone_full": "+201000000100",
      "city_id": 1,
      "city": "Cairo",
      "address": "St 1",
      "notes": null,
      "is_default": true
    }
  ],
  "meta": {}
}
```

### `POST /addresses`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Create an address (the first one becomes default).

**Body (`*` = required):** `name`*, `phone`*, `phone_country_code`, `city_id`*, `address`*, `label`, `notes`, `is_default`

**Notes:** Rate limit 30/min.

**Example response (201):**

```json
{
  "success": true,
  "message": "Saved successfully.",
  "data": {
    "id": 1,
    "label": "Home",
    "name": "Ali",
    "phone": "01000000100",
    "phone_country_code": "+20",
    "phone_full": "+201000000100",
    "city_id": 1,
    "city": "Cairo",
    "address": "St 1",
    "notes": null,
    "is_default": true
  },
  "meta": {}
}
```

### `PUT /addresses/{id}`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Update an address (another customer's id = 404).

**Body (`*` = required):** Same as create

### `DELETE /addresses/{id}`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Delete an address.

### `GET /wishlist`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Wishlist products.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "slug": "cheap",
      "name": "P cheap",
      "image": null,
      "price": "100.00",
      "old_price": null,
      "discount_percent": null,
      "flash": false,
      "flash_ends_at": null,
      "rating": null,
      "reviews_count": 0
    }
  ],
  "meta": {}
}
```

### `POST /wishlist/{product}/toggle`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Add/remove a product (`{product}` = numeric product id). Returns `in_wishlist`.

**Example response (200):**

```json
{
  "success": true,
  "message": "Added to wishlist.",
  "data": {
    "in_wishlist": true
  },
  "meta": {}
}
```

### `POST /products/{slug}/reviews`

**Auth:** Customer bearer token  
**Host:** `APP_URL/api/v1`

Write a review (delivered purchase only, one per product; goes to moderation).

**Body (`*` = required):** `rating`* (1-5), `body`

**Notes:** Rate limit 10/min.

**Example response (201):**

```json
{
  "success": true,
  "message": "Thanks! Your review will appear after moderation.",
  "data": {
    "id": 1,
    "rating": 5,
    "body": "Great product",
    "status": "pending",
    "author": null,
    "created_at": "2026-11-01T23:51:45+02:00"
  },
  "meta": {}
}
```

### `POST /devices`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Register/refresh a push token (FCM). Upsert by token within the store; with a bearer token the device is bound to the customer. Call after login and whenever FCM rotates the token. Sending is not implemented yet (see Push).

**Body (`*` = required):** `token`* (20-512 chars), `platform`* (`android|ios|web`), `locale`, `app_version`

**Notes:** Rate limit 30/min.

**Example response (201):**

```json
{
  "success": true,
  "message": "Saved successfully.",
  "data": {
    "id": 1,
    "platform": "android",
    "locale": "ar",
    "bound_to_customer": true
  },
  "meta": {}
}
```

### `DELETE /devices`

**Auth:** Optional customer bearer token  
**Host:** `APP_URL/api/v1`

Unregister a push token (call on logout / opt-out). Idempotent.

**Body (`*` = required):** `token`*

**Example response (200):**

```json
{
  "success": true,
  "message": "Deleted successfully.",
  "data": null,
  "meta": {}
}
```


---

## Affiliate (separate token, ability `affiliate`)

### `POST /affiliate/register`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Apply as an affiliate. No token until an admin approves.

**Body (`*` = required):** `name`*, `email`*, `password`*, `password_confirmation`*, `phone`, `phone_country_code`

**Example response (201):**

```json
{
  "success": true,
  "message": "Registered. Your account is awaiting approval.",
  "data": {
    "id": 2,
    "name": "New",
    "email": "newaff@x.test",
    "phone": null,
    "phone_country_code": null,
    "phone_full": null,
    "status": "pending",
    "ref": "b898c8e1-9c85-4582-87da-cff21b70fb32"
  },
  "meta": {}
}
```

### `POST /affiliate/login`

**Auth:** None  
**Host:** `APP_URL/api/v1`

Sign in (approved affiliates only).

**Body (`*` = required):** `email`*, `password`*

**Example response (200):**

```json
{
  "success": true,
  "message": "Welcome back!",
  "data": {
    "token": "12|AbCdEf...",
    "token_type": "Bearer",
    "abilities": [
      "affiliate"
    ],
    "affiliate": {
      "id": 1,
      "name": "Demo Affiliate",
      "email": "aff@egy.test",
      "phone": null,
      "phone_country_code": null,
      "phone_full": null,
      "status": "approved",
      "ref": "b898c8e1-9c85-4582-87da-cff21b70fb32"
    }
  },
  "meta": {}
}
```

### `POST /affiliate/logout`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Revoke the token.

### `GET /affiliate/me`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Profile; `ref` is the uuid used in share links / `X-Affiliate-Ref`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Demo Affiliate",
    "email": "aff@egy.test",
    "phone": null,
    "phone_country_code": null,
    "phone_full": null,
    "status": "approved",
    "ref": "b898c8e1-9c85-4582-87da-cff21b70fb32"
  },
  "meta": {}
}
```

### `GET /affiliate/dashboard`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Stats, tier, commissions, payouts.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "affiliate": {
      "id": 1,
      "name": "Demo Affiliate",
      "email": "aff@egy.test",
      "phone": null,
      "phone_country_code": null,
      "phone_full": null,
      "status": "approved",
      "ref": "b898c8e1-9c85-4582-87da-cff21b70fb32"
    },
    "stats": {
      "clicks": 0,
      "orders": 0,
      "conversion_percent": 0,
      "lifetime_sales": "0.00",
      "tier": {
        "name": "Starter",
        "percent": "5.00",
        "min_sales": "0.00"
      },
      "next_tier": {
        "name": "Silver",
        "percent": "7.00",
        "min_sales": "1000.00"
      },
      "commissions": {
        "pending": "0.00",
        "approved": "0.00",
        "paid": "0.00",
        "available": "0.00"
      }
    },
    "payouts": [],
    "recent_commissions": []
  },
  "meta": {}
}
```

### `GET /affiliate/products`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Sellable products with the affiliate share `link` and `qr_url`.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2,
      "slug": "mid",
      "name": "P mid",
      "image": null,
      "price": "250.00",
      "link": "http://3alraf.test/en/product/mid?utm_source=affiliate&aff=b898c8e1-9c85-4582-87da-cff21b70fb32",
      "qr_url": "http://3alraf.test/api/v1/affiliate/products/mid/qr",
      "variants": []
    }
  ],
  "meta": {}
}
```

### `GET /affiliate/products/{slug}/qr`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

PNG QR of the share link (binary).

**Notes:** Binary response (`image/png`).

### `GET /affiliate/payouts`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Payout history.

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {}
}
```

### `POST /affiliate/payouts`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Request a payout of the approved balance. 422 when nothing is available.

**Notes:** Rate limit 5/min.

**Example response (422):**

```json
{
  "success": false,
  "message": "No approved commission available for payout.",
  "errors": {
    "amount": [
      "No approved commission available for payout."
    ]
  },
  "data": null,
  "meta": {}
}
```

### `GET /affiliate/ambassador`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Ambassador portal (feature `ambassadors`, else 403): enrolment state, allowed `channels`, idea library, own tracked links and a 30-day report (valid/pending/excluded orders, new-customer share, CAC; no margins).

**Example response (200):**

```json
{
  "success": true,
  "message": null,
  "data": {
    "enrolled": true,
    "ambassador": {
      "status": "active",
      "disclosure": "I am a paid brand partner",
      "terms_accepted_at": "2026-11-01T23:51:45+02:00"
    },
    "channels": [
      "instagram"
    ],
    "ideas": [],
    "links": [
      {
        "id": 1,
        "channel": "instagram",
        "product_id": 1,
        "clicks": 0,
        "url": "http://3alraf.test/l/0kcv8vr0"
      }
    ],
    "report": {
      "valid_orders": 0,
      "pending_orders": 0,
      "excluded_orders": 0,
      "new_customer_share": null,
      "window_days": 14,
      "referral_cac": null,
      "by_channel": {
        "instagram": {
          "clicks": 0,
          "orders": 0
        }
      },
      "disclosure": "I am a paid brand partner"
    }
  },
  "meta": {}
}
```

### `POST /affiliate/ambassador/join`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Opt in as an ambassador (disclosure + terms). Idempotent re-join updates the disclosure.

**Body (`*` = required):** `disclosure`* (max 300; texts that reward fake reviews are refused with 422), `terms_accepted`* (true)

**Example response (201):**

```json
{
  "success": true,
  "message": "Welcome, you are now an ambassador.",
  "data": {
    "status": "active",
    "disclosure": "I am a paid brand partner",
    "terms_accepted_at": "2026-11-01T23:51:45+02:00"
  },
  "meta": {}
}
```

### `POST /affiliate/ambassador/links`

**Auth:** Affiliate bearer token  
**Host:** `APP_URL/api/v1`

Create a per-channel tracked link for a product (404 when not enrolled). `url` is a short link on the store domain.

**Body (`*` = required):** `product_id`*, `channel`* (one of `channels`, anything else is stored as `other`)

**Example response (201):**

```json
{
  "success": true,
  "message": "Saved successfully.",
  "data": {
    "id": 1,
    "channel": "instagram",
    "product_id": 1,
    "clicks": 0,
    "url": "http://3alraf.test/l/0kcv8vr0"
  },
  "meta": {}
}
```


---

## Appendix A - Revision log (Phase 1 gap closure)

Added: `GET /blog`, `GET /blog/{slug}`; `GET /categories/{slug}`, `GET /brands/{slug}`, `GET /tags/{slug}`; `brand` and `tag` filters on `catalog` and `catalog/facets`; `POST /stock-requests`; `GET|POST /launch/{code}`; `GET /affiliate/ambassador`, `POST /affiliate/ambassador/join`, `POST /affiliate/ambassador/links`; `GET /orders/{token}/invoice`, `GET /orders/{token}/proof`; `DELETE /auth/me`; `POST|DELETE /devices`; `GET /app-config`.
Extended: `GET /store` (logo, favicon, colours, contact, social, app links, footer, features); `GET /tenants` (`flag`, `languages`, `logo_url`, enveloped).
Single store (2026-11): `X-Tenant` ignored, legacy store hosts served directly, `GET /app-config` gained `store`, `GET /tenants` deprecated (one-item list, old shape, `Deprecation` header), no per-store token/cart scoping.
Fixed: the `store.api.*` translation group was missing, so API messages such as logout / reset / verification returned raw keys; ar/en strings added. 404 messages no longer expose model class names.

## Appendix B - Known limits / not in this API

* Notifications inbox, push sending (FCM), customer phone/OTP login: not provided.
* `DELETE /auth/me` anonymises the account but keeps order rows (their name/phone/address snapshots) for accounting; purge policy follows the store's retention job.
* Change password is `PUT /auth/me` (there is no separate endpoint).
* `GET /store` `colors` can be overridden through setting `branding.colors` and contact WhatsApp through setting `contact.whatsapp`; there is no admin UI for these two keys yet.
