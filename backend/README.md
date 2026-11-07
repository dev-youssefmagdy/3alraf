# 3alraf - online store (Laravel 13, single store)

One shop on one host (`APP_URL`): admin panel `/admin`, storefront `/{locale}` (`/` → default locale), mobile API `/api/v1`,
n8n integration API `/api/integration/v1`. Shop settings (name, country, currency, locales, timezone, payments, SEO, AI/Growth switches)
are in **Settings → General** (`settings` table, helper `setting()`). Until 2026-11 the project was multi-tenant; see
`docs/REQUIREMENTS.md` §2 and `/var/www/3alraf/REMOVE_MULTI_TENANCY_PLAN.md` for the conversion.

## How to run

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # DB: MySQL db `3alraf`; APP_URL=http://3alraf.test (any host)
php artisan migrate:fresh --seed                   # general settings (EG / EGP, ar+en), admins, demo operations, cities, gift catalog
php artisan storage:link
npm run build                                      # or `npm run dev`
php artisan serve --host=127.0.0.1 --port=8000     # or any vhost pointing at public/
vendor/bin/pest                                    # tests use MySQL db `3alraf_test`
```

Other seed modes: `php artisan seed:dev` (fake data for everything, local/staging) and `php artisan seed:live --force` (removes demo data, keeps live data).
Real super admins: `php artisan admin:provision-super` (exactly one; created with `otp_exempt`, like the former platform super admin, so the first login works before WhatsApp is paired — an owner/super admin can clear the flag on Team & access → Admins).

| Area | URL | Login |
|---|---|---|
| Admin panel | APP_URL/admin | Super Admin phone 201000000001 / password (owner role: 201000000101 / password) |
| Storefront | APP_URL/ar , /en | customer@egy.test / password |
| Affiliate | APP_URL/ar/affiliate/login | aff@egy.test / password |

Legacy hosts: old shop subdomains (`egy.3alraf.test`, …) answer 301 to `APP_URL` (`LegacyHostRedirect`); `webhooks/*` and `api/*` are served
directly there. Deploy: `deploy/nginx/app.conf` (site vhost + legacy subdomain block), `docker-compose.yml`, `deploy/supervisor/3alraf.conf`.

Demo discount code `WELCOME10`. Re-run `php artisan db:seed --class=PermissionSeeder` after permission changes. Browser smoke test (headless Chrome via playwright-core) is described in docs/PROGRESS.md.

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
