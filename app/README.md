# 3alraf customer app (عالرف)

Flutter 3.41 / Dart ^3.11 customer app for the 3alraf store (one shop, one
host). Every API call goes to `API_BASE/api/v1`; the shop basics (name,
country, currency, locales + RTL, timezone, base URL) come from
`GET /api/v1/app-config` (`store` block) and are cached for offline starts.

### Startup flow

`splash` -> one-time legacy storage migration -> `GET /app-config?version=`
(maintenance / forced update gates + `store`, cached) -> background
`GET /store` (contact, features, app links, cached) -> **language page only if
the user never chose a language and the store offers more than one** (choices =
store `locales` the app translates; default = `default_locale`) -> onboarding
(first launch) -> Home. Offline with a cached store: Home with a retry banner;
offline and nothing cached: full-screen retry. Currency (`currencyLabelProvider`),
locale and text direction (store `rtl` flag) all come from the store info.

### Upgrading from the multi-store builds

Older builds saved a store slug and namespaced keys as `<key>:<slug>`. On the
first launch of this build `migrateLegacyStorage()`
(`lib/core/storage/legacy_storage_migration.dart`) moves the auth / affiliate /
guest-cart tokens (secure storage), the chosen locale, the `/store` cache and
recent searches to the plain keys and drops the slug, so customers stay signed
in and keep their cart and language. It runs once (`storage_single_store_v1`).
The backend still answers old builds (`X-Tenant` ignored, deprecated
`/api/v1/tenants` alias) - see `../backend/docs/MOBILE_API.md` section 1.

Package / bundle id: `com.alraf.shop`. Display name: `عالرف / 3alraf`.
Contract with the backend: `../backend/docs/MOBILE_API.md` and
`../backend/docs/openapi-mobile.yaml`.

## Setup

```bash
export PATH="$PATH:/home/joe/flutter/bin"   # or your Flutter 3.41 install
cd app
flutter pub get
flutter gen-l10n                            # generated automatically by build/run too
dart run build_runner build --delete-conflicting-outputs   # only if you change freezed/json models
```

Android needs a JDK 17 and the Android SDK; iOS needs macOS + Xcode.

## `--dart-define` values

| Key | Default | Meaning |
|---|---|---|
| `API_BASE` | `https://3alraf.com` | The store host = backend `APP_URL` (no `/api/v1`). Local examples: `http://10.0.2.2:8000` (Android emulator), `http://192.168.x.x:8000`. Every call (`/app-config`, `/store`, catalog, auth...) goes to `{API_BASE}/api/v1`. |
| `GOOGLE_SERVER_CLIENT_ID` | empty | OAuth **Web** client id. Empty hides Google sign-in. Must equal the backend `GOOGLE_CLIENT_ID` (or be listed in `GOOGLE_MOBILE_CLIENT_IDS`) because the ID token audience is checked by `POST /auth/google`. |
| `ENV` | `prod` | Free-form label (`dev`, `staging`, `prod`). |

## Run

```bash
flutter run --dart-define=API_BASE=http://10.0.2.2:8000 --dart-define=ENV=dev
```

Debug builds allow plain-http backends (`usesCleartextTraffic` is set only in
`android/app/src/debug`). Release builds are https-only. For local backends
fill Settings -> General (name, currency, locales) so `app-config.store` is
complete.

## Quality gates

```bash
flutter analyze        # must be clean
flutter test           # 128 tests
dart format lib test
```

Backend contract tests: `cd ../backend && php artisan test tests/Feature/MobileApiTest.php tests/Feature/MobileApiGapsTest.php`.

## Build and release

```bash
flutter build apk --debug
flutter build apk --release   --dart-define=API_BASE=https://3alraf.com --dart-define=GOOGLE_SERVER_CLIENT_ID=... 
flutter build appbundle --release --dart-define=API_BASE=https://3alraf.com --dart-define=GOOGLE_SERVER_CLIENT_ID=...
flutter build ipa --release   --dart-define=API_BASE=https://3alraf.com --dart-define=GOOGLE_SERVER_CLIENT_ID=...
```

Bump `version:` in `pubspec.yaml` (`1.0.0+1`: name + build number) for every
store upload. The backend `config/mobile.php` / `GET /app-config`
(`min_version`, `latest_version`, `store_urls`, `maintenance`) drives the
force-update and maintenance screens, so raise `min_version` there when an old
build must be retired.

### Android signing

1. Create a keystore once and keep it out of git:
   `keytool -genkey -v -keystore ~/alraf-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload`
2. Create `android/key.properties` (git-ignored):

   ```properties
   storeFile=/home/you/alraf-upload.jks
   storePassword=...
   keyAlias=upload
   keyPassword=...
   ```

3. `android/app/build.gradle.kts` reads it automatically for `release`. Without
   the file release builds fall back to the debug key (fine for local testing,
   **not** for the Play Store). Enrol in Play App Signing and print the SHA-256
   with `keytool -list -v -keystore ~/alraf-upload.jks` (needed below).

### iOS signing

Open `ios/Runner.xcworkspace`, select the Runner target, set the team and
keep the bundle id `com.alraf.shop`. Archive from Xcode or `flutter build ipa`.

### Icons and splash

Sources are in `assets/branding/`. After changing them:

```bash
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```

## Push notifications (Firebase / FCM)

The app ships with a no-op `PushTokenProvider`; nothing is registered until you
wire Firebase. The backend already stores tokens (`POST/DELETE /devices`) with
a log-only `PushDispatcher`; **sending** is a backend follow-up.

1. Create a Firebase project and add an Android app (`com.alraf.shop`) and an
   iOS app (`com.alraf.shop`).
2. Download `google-services.json` to `android/app/` and `GoogleService-Info.plist`
   to `ios/Runner/` (add it to the Runner target in Xcode). Do not commit them
   if the repo is public.
3. `flutter pub add firebase_core firebase_messaging`, then
   Android: add the `com.google.gms.google-services` plugin to
   `android/settings.gradle.kts` and `android/app/build.gradle.kts`;
   iOS: enable the Push Notifications and Background Modes (remote
   notifications) capabilities and upload an APNs key in the Firebase console.
4. Implement `PushTokenProvider` (`lib/features/push/data/push_service.dart`):
   call `Firebase.initializeApp()`, request permission, return
   `FirebaseMessaging.instance.getToken()`, and call
   `PushRegistrar.register()` again on `onTokenRefresh`.
5. Override the provider in `main.dart`:
   `pushTokenProviderProvider.overrideWithValue(FcmPushTokenProvider())`.

Behaviour already in place: the token is registered after login / session
restore / registration / Google sign-in, re-registered when the user changes
language (so the server can localise notifications), and removed before logout
or account deletion. All push failures are swallowed.

## Google sign-in

1. In Google Cloud Console create OAuth clients: **Web** (its id is
   `GOOGLE_SERVER_CLIENT_ID`), **Android** (package `com.alraf.shop`, SHA-1 of
   the debug, upload and Play-signing keys) and **iOS** (bundle id
   `com.alraf.shop`).
2. Backend `.env`: `GOOGLE_CLIENT_ID=<web client id>` and
   `GOOGLE_MOBILE_CLIENT_IDS=<android id>,<ios id>`.
3. iOS: add `GIDClientID` (the iOS client id) to `ios/Runner/Info.plist` and the
   reversed client id as an extra `CFBundleURLSchemes` entry.
4. Build with `--dart-define=GOOGLE_SERVER_CLIENT_ID=<web client id>`.

Not tested on a real device yet.

## Deep links

Supported: `https://{slug}.3alraf.com/{ar|en}/...` and `https://3alraf.com/...`
(product, category, brand, tag, order, page, blog, launch, search) plus the
`alraf://` scheme. Routing lives in `lib/core/routing/link_router.dart`.

### Android App Links

The manifest already declares the verified https intent filter. Each host
(`3alraf.com` and every store sub-domain) must serve **without redirect**,
`Content-Type: application/json`:

`https://{host}/.well-known/assetlinks.json`

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.alraf.shop",
    "sha256_cert_fingerprints": ["<SHA-256 of the Play app-signing key>", "<SHA-256 of the upload key>"]
  }
}]
```

The Laravel app has no route for it: serve it as a static file from the web
server for every host (e.g. an nginx `location = /.well-known/assetlinks.json`
with `alias`, shared by the wildcard `*.3alraf.com` server block). Verify with
`adb shell pm get-app-links com.alraf.shop` and
`adb shell am start -a android.intent.action.VIEW -d "https://egy.3alraf.com/ar/product/x"`.

### iOS Universal Links

`ios/Runner/Runner.entitlements` already lists the associated domains
(`applinks:3alraf.com`; `egy.3alraf.com` / `sa.3alraf.com` are legacy hosts
of the old per-country stores, kept so already shared links still open the app
- the server 301s them to the main host) but it is **not referenced by the Xcode project**, because associated
domains cannot be signed with a free Apple team. To enable:

1. Paid Apple Developer team selected for Runner; enable the *Associated
   Domains* capability (Xcode adds the entitlement).
2. In `ios/Runner.xcodeproj/project.pbxproj`, in the Debug, Release and Profile
   build settings of the Runner target add
   `CODE_SIGN_ENTITLEMENTS = Runner/Runner.entitlements;` (or add the file in
   Xcode, which does it for you).
3. Host `https://{host}/.well-known/apple-app-site-association` on every host
   (no extension, `Content-Type: application/json`, no redirect, https):

   ```json
   {
     "applinks": {
       "details": [{
         "appIDs": ["<TEAMID>.com.alraf.shop"],
         "components": [{ "/": "/*" }]
       }]
     }
   }
   ```

4. Test on a device (links do not work in the simulator reliably):
   `xcrun simctl openurl booted "https://egy.3alraf.com/ar/product/x"`.

## Project layout

```
lib/core/{config,network,storage,theme,l10n,routing,widgets,utils,error}
lib/features/<feature>/{data,application,presentation}
test/               unit + widget tests (test/support = fake backend harness)
assets/             Cairo font, branding sources
```

Rules of thumb: money is always a server string (never computed or stored as
`double`), secrets live in secure storage (`SessionStorage`: `auth_token`,
`affiliate_token`, `cart_token`), and every user-visible string is an ARB key in `lib/l10n`
(`ar` template, `en`).
