<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use ReflectionMethod;

/** Builds docs/api/openapi.json (OpenAPI 3.0) from the routes named api.v1.* (+ docblocks and FormRequest rules). Run: php artisan api:docs */
class GenerateApiDocs extends Command
{
    protected $signature = 'api:docs {--path=docs/api/openapi.json}';

    protected $description = 'Generate the OpenAPI document of the mobile API';

    public function handle(): int
    {
        if ($tenant = Tenant::query()->first()) {
            app()->instance('currentTenant', $tenant); // FormRequest rules read current_tenant()
        }
        $paths = [];
        foreach (Router::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'api.v1.') || str_starts_with($name, 'api.v1.h.')) {
                continue; // the X-Tenant flavour is the same API
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
                ['url' => 'https://{tenant}.'.config('app.central_domain'), 'description' => 'Tenant from the host', 'variables' => ['tenant' => ['default' => 'egy']]],
                ['url' => 'https://'.config('app.central_domain'), 'description' => 'Central host: send the X-Tenant header'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'customerToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum token with ability `customer` (POST /auth/login)'],
                    'affiliateToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum token with ability `affiliate` (POST /affiliate/login)'],
                ],
                'parameters' => [
                    'XTenant' => ['name' => 'X-Tenant', 'in' => 'header', 'required' => false, 'description' => 'Store slug when calling the central host', 'schema' => ['type' => 'string', 'example' => 'egy']],
                    'AcceptLanguage' => ['name' => 'Accept-Language', 'in' => 'header', 'required' => false, 'description' => 'ar | en (limited to the store locales)', 'schema' => ['type' => 'string', 'example' => 'ar']],
                    'XCartToken' => ['name' => 'X-Cart-Token', 'in' => 'header', 'required' => false, 'description' => 'Guest cart token, returned in the X-Cart-Token response header', 'schema' => ['type' => 'string']],
                    'XAffiliateRef' => ['name' => 'X-Affiliate-Ref', 'in' => 'header', 'required' => false, 'description' => 'Affiliate ref (uuid) of a shared link, for commission attribution', 'schema' => ['type' => 'string']],
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
        $summary = \Illuminate\Support\Str::headline(str_replace('.', ' ', $short));
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
        foreach (['XTenant', 'AcceptLanguage'] as $p) {
            $params[] = ['$ref' => "#/components/parameters/$p"];
        }
        if (str_contains($middleware, 'api.context')) {
            $params[] = ['$ref' => '#/components/parameters/XCartToken'];
            $params[] = ['$ref' => '#/components/parameters/XAffiliateRef'];
        }
        preg_match_all('#\{(\w+)\??\}#', $uri, $m);
        foreach ($m[1] as $p) {
            if ($p !== 'tenant') {
                $params[] = ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => in_array($p, ['item', 'id', 'product']) ? 'integer' : 'string']];
            }
        }
        $op = ['summary' => rtrim($summary, '.'), 'operationId' => str_replace('.', '_', $short).'_'.strtolower($method), 'tags' => [explode('/', preg_replace('#^api/v1/#', '', $uri))[0] ?: 'misc'], 'parameters' => $params];
        if ($security) {
            $op['security'] = $security;
        }
        if ($request && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $schema = $this->schema($request);
            $multipart = str_contains(json_encode($schema), '"format":"binary"'); // file upload => multipart/form-data
            $op['requestBody'] = ['content' => [$multipart ? 'multipart/form-data' : 'application/json' => ['schema' => $schema]]];
        }
        $op['responses'] = ['200' => ['description' => 'Success (envelope)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]]],
            '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]]];
        if ($security && $security !== [[], ['customerToken' => []]]) {
            $op['responses']['401'] = ['description' => 'Missing / wrong-type / other-tenant token', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
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
            ."Tenant: use the store host ({tenant}.domain) or the central host plus the `X-Tenant` slug header. GET /api/v1/tenants (central) lists the stores.\n"
            ."Envelope: every response is {success, message, data, meta}; errors are {success:false, message, errors?, data:null, meta:{}} with 401/403/404/422/429.\n"
            ."Auth: Sanctum bearer tokens of two kinds. Customer tokens (ability `customer`, from /auth/login or /auth/register) and affiliate tokens (ability `affiliate`, from /affiliate/login) are not interchangeable and only work in the tenant that issued them.\n"
            ."Guests: send the `X-Cart-Token` returned by the first cart call; after login call POST /cart/merge (or send it with /auth/login) to merge it.\n"
            ."Checkout: place the order, then follow meta.next_action: redirect_to_payment (open meta.payment_url, then poll GET /payments/return/{token} until meta.paid), upload_proof (re-upload of a rejected wallet transfer proof, POST /orders/{token}/proof), retry_payment (POST /orders/{token}/pay), none (COD). Orders always wait for the store admin to confirm.\n"
            ."Offline payment: a wallet / bank-transfer payment method (or the wallet prepay leg of COD) REQUIRES the transfer proof file as the multipart field `proof` (jpg, png, webp or pdf) on POST /checkout and POST /checkout/validate; 422 `proof` when missing, invalid or already used for another order; 429 + Retry-After when the anti-abuse limiter trips. Bundles: add with POST /cart/items {bundle_id, quantity}; special packaging: GET /checkout/packaging then send packaging_option_id.\n"
            ."Money values are decimal strings in the store currency. Cost, profit and commission fields are never exposed.";
    }
}
