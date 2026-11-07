<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use ReflectionMethod;

/** Builds docs/api/openapi.json (OpenAPI 3.0) from the routes named api.v1.* (+ docblocks and FormRequest rules). Run: php artisan api:docs */
class GenerateApiDocs extends Command
{
    protected $signature = 'api:docs {--path=docs/api/openapi.json}';

    protected $description = 'Generate the OpenAPI document of the mobile API';

    public function handle(): int
    {
        $paths = [];
        foreach (Router::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'api.v1.')) {
                continue;
            }
            $path = '/'.preg_replace('#^api/v1/?#', 'api/v1/', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $name, $method);
            }
        }
        ksort($paths);
        $doc = [
            'openapi' => '3.0.3',
            'info' => ['title' => '3alraf Mobile API', 'version' => '1.0.0', 'description' => $this->intro()],
            'servers' => [
                ['url' => rtrim((string) config('app.url'), '/'), 'description' => 'Site host'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'customerToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum token with ability `customer` (POST /auth/login)'],
                    'affiliateToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum token with ability `affiliate` (POST /affiliate/login)'],
                ],
                'parameters' => [
                    'AcceptLanguage' => ['name' => 'Accept-Language', 'in' => 'header', 'required' => false, 'description' => 'ar | en (limited to the store locales)', 'schema' => ['type' => 'string', 'example' => 'ar']],
                    'XCartToken' => ['name' => 'X-Cart-Token', 'in' => 'header', 'required' => false, 'description' => 'Guest cart token, returned in the X-Cart-Token response header', 'schema' => ['type' => 'string']],
                    'XAffiliateRef' => ['name' => 'X-Affiliate-Ref', 'in' => 'header', 'required' => false, 'description' => 'Affiliate ref (uuid) of a shared link, for commission attribution', 'schema' => ['type' => 'string']],
                    'XAttribution' => ['name' => 'X-Attribution', 'in' => 'header', 'required' => false, 'description' => 'Growth first-touch attribution: base64url(JSON) of {utm_source, utm_medium, utm_campaign, utm_content, ref, link, gift_session, ab}, max 1024 chars. Stored on the cart (first touch wins) and copied to the order channel at checkout. Malformed values are ignored.', 'schema' => ['type' => 'string', 'maxLength' => 1024]],
                ],
                'schemas' => [
                    'Envelope' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'nullable' => true], 'data' => ['nullable' => true], 'meta' => ['type' => 'object']]],
                    'Error' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean', 'example' => false], 'message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]], 'data' => ['nullable' => true], 'meta' => ['type' => 'object']]],
                ],
            ],
        ];
        $file = base_path($this->option('path'));
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info('Wrote '.$this->option('path').' ('.count($paths).' paths)');

        return self::SUCCESS;
    }

    private function operation(Route $route, string $name, string $method): array
    {
        $uri = $route->uri();
        $short = preg_replace('#^api\.v1\.#', '', $name);
        [$class, $action] = explode('@', $route->getActionName().'@');
        $action = $action === '' && $class && method_exists($class, '__invoke') ? '__invoke' : $action; // invokable controllers
        $summary = Str::headline(str_replace('.', ' ', $short));
        $request = null;
        if ($class && $action && method_exists($class, $action)) {
            $ref = new ReflectionMethod($class, $action);
            if (preg_match('#/\*\*\s*(.+?)\s*\*/#s', (string) $ref->getDocComment(), $m)) {
                $summary = trim(preg_replace('#\s*\n\s*\*\s*#', ' ', $m[1]));
            }
            foreach ($ref->getParameters() as $p) {
                $t = $p->getType()?->getName();
                if ($t && is_subclass_of($t, FormRequest::class)) {
                    $request = $t;
                }
            }
        }
        $middleware = implode(' ', $route->gatherMiddleware());
        $security = match (true) {
            str_contains($middleware, 'api.auth:affiliate') => [['affiliateToken' => []]],
            str_contains($middleware, 'api.auth:customer,optional') => [[], ['customerToken' => []]],
            str_contains($middleware, 'api.auth:customer') => [['customerToken' => []]],
            default => [],
        };
        $params = [];
        foreach (['AcceptLanguage'] as $p) {
            $params[] = ['$ref' => "#/components/parameters/$p"];
        }
        if (str_contains($middleware, 'api.context')) {
            $params[] = ['$ref' => '#/components/parameters/XCartToken'];
            $params[] = ['$ref' => '#/components/parameters/XAffiliateRef'];
            $params[] = ['$ref' => '#/components/parameters/XAttribution'];
        }
        preg_match_all('#\{(\w+)\??\}#', $uri, $m);
        foreach ($m[1] as $p) {
            $params[] = ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => in_array($p, ['item', 'id', 'product']) ? 'integer' : 'string']];
        }
        $op = ['summary' => rtrim($summary, '.'), 'operationId' => str_replace('.', '_', $short).'_'.strtolower($method), 'tags' => [explode('/', preg_replace('#^api/v1/#', '', $uri))[0] ?: 'misc'], 'parameters' => $params];
        if ($security) {
            $op['security'] = $security;
        }
        if ($name === 'api.v1.legacy-stores') {
            $op['deprecated'] = true; // old app builds only (country picker); new builds use app-config / store
        }
        if ($request && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $schema = $this->schema($request);
            $multipart = str_contains(json_encode($schema), '"format":"binary"'); // file upload => multipart/form-data
            $op['requestBody'] = ['content' => [$multipart ? 'multipart/form-data' : 'application/json' => ['schema' => $schema]]];
        }
        $op['responses'] = ['200' => ['description' => 'Success (envelope)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]]],
            '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]]];
        if ($security && $security !== [[], ['customerToken' => []]]) {
            $op['responses']['401'] = ['description' => 'Missing / wrong-type token', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
        }

        return $op;
    }

    private function schema(string $class): array
    {
        try {
            $rules = (new $class)->rules();
        } catch (\Throwable) {
            return ['type' => 'object'];
        }
        $props = [];
        $required = [];
        foreach ($rules as $field => $rule) {
            if (str_contains($field, '.')) {
                continue;
            }
            $r = array_map(fn ($x) => is_string($x) ? $x : '', is_array($rule) ? $rule : explode('|', (string) $rule));
            $has = fn (string ...$k) => (bool) array_intersect($k, $r);
            $type = match (true) {
                $has('integer') => 'integer', $has('numeric') => 'number', $has('boolean') => 'boolean', $has('array') => 'array', default => 'string',
            };
            $props[$field] = ['type' => $type] + ($has('file') ? ['format' => 'binary'] : []) + ($has('email') ? ['format' => 'email'] : []) + ($type === 'array' ? ['items' => (object) []] : []);
            if ($has('required')) {
                $required[] = $field;
            }
        }
        $schema = ['type' => 'object', 'properties' => (object) $props];
        if (collect($rules)->contains(fn ($r) => is_array($r) && in_array('file', $r, true))) {
            return ['type' => 'object', 'properties' => (object) $props] + ($required ? ['required' => $required] : []);
        }

        return $schema + ($required ? ['required' => $required] : []);
    }

    private function intro(): string
    {
        return "JSON API for the Flutter apps.\n\n"
            ."Host: one shop on APP_URL. GET /api/v1/app-config returns the app switches plus `store` {name, country, currency{code,symbol}, default_locale, locales[{code,rtl}], timezone, base_url}; GET /api/v1/store returns the full shop profile. "
            ."The `X-Tenant` header of old app builds and old `{slug}.` hosts are ignored (the API answers on legacy hosts too; generated URLs always use APP_URL). "
            ."GET /api/v1/tenants is DEPRECATED: a one-item list in the old shape (slug, name, country, flag, currency, default_locale, locales, languages, logo_url, base_url) for old app builds.\n"
            ."Envelope: every response is {success, message, data, meta}; errors are {success:false, message, errors?, data:null, meta:{}} with 401/403/404/422/429.\n"
            ."Auth: Sanctum bearer tokens of two kinds. Customer tokens (ability `customer`, from /auth/login or /auth/register) and affiliate tokens (ability `affiliate`, from /affiliate/login) are not interchangeable.\n"
            ."Guests: send the `X-Cart-Token` returned by the first cart call; after login call POST /cart/merge (or send it with /auth/login) to merge it.\n"
            ."Checkout: place the order, then follow meta.next_action: redirect_to_payment (open meta.payment_url, then poll GET /payments/return/{token} until meta.paid), upload_proof (re-upload of a rejected wallet transfer proof, POST /orders/{token}/proof), retry_payment (POST /orders/{token}/pay), none (COD). Orders always wait for the store admin to confirm.\n"
            ."Offline payment: a wallet / bank-transfer payment method (or the wallet prepay leg of COD) REQUIRES the transfer proof file as the multipart field `proof` (jpg, png, webp or pdf) on POST /checkout and POST /checkout/validate; 422 `proof` when missing, invalid or already used for another order; 429 + Retry-After when the anti-abuse limiter trips. Bundles: add with POST /cart/items {bundle_id, quantity}; special packaging: GET /checkout/packaging then send packaging_option_id.\n"
            .'Money values are decimal strings in the store currency. Cost, profit and commission fields are never exposed.';
    }
}
