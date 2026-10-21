# 3alraf - multi-tenant marketplace (Laravel 13)

## How to run

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # DB: MySQL db `3alraf`; APP_CENTRAL_DOMAIN=3alraf.test
php artisan migrate:fresh --seed                   # ~30s: 2 tenants, 40 products, 75 variants, 52 categories, banners, orders (images are generated locally with GD)
php artisan storage:link
npm run build                                      # or `npm run dev`
php artisan serve --host=127.0.0.1 --port=8000     # or any vhost pointing at public/
vendor/bin/pest                                    # tests use MySQL db `3alraf_test`
```

Hosts (NOT done automatically; add to `/etc/hosts` yourself, vhost on port 80 or use `:8000` with `artisan serve`):

```
127.0.0.1 3alraf.test egy.3alraf.test sa.3alraf.test
```

Alternative without editing hosts: set `APP_CENTRAL_DOMAIN=localhost` (Chrome/Firefox resolve `*.localhost` to loopback) and use `http://egy.localhost:8000`.
Without a browser: `curl --resolve egy.3alraf.test:8000:127.0.0.1 http://egy.3alraf.test:8000/en`.

| Area | URL | Login |
|---|---|---|
| Central admin | http://3alraf.test/admin | admin@3alraf.test / password |
| Tenant `egy` (EGP, ar/en) storefront | http://egy.3alraf.test/ar , /en | customer@egy.test / password |
| Tenant `egy` admin | http://egy.3alraf.test/admin | owner@egy.test / password |
| Tenant `sa` (SAR, ar/en) | http://sa.3alraf.test/ar ; admin `/admin` | owner@sa.test / password, customer@sa.test |
| Affiliate (per tenant) | /ar/affiliate/login | aff@egy.test / password |

Demo discount code `WELCOME10`. Seeders are idempotent per phase; re-run `php artisan db:seed --class=PermissionSeeder` after permission changes. Browser smoke test (headless Chrome via playwright-core) is described in docs/PROGRESS.md (Phase 8).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
