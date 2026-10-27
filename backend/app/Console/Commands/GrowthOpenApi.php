<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/** Generates docs/api/integration-openapi.json from the integration routes + their FormRequest rules (request bodies, path/query params, envelopes, errors, auth, scopes). */
class GrowthOpenApi extends Command
{
    protected $signature = 'growth:openapi {--stdout : print instead of writing the file}';

    protected $description = 'Generate the integration API OpenAPI document from routes and FormRequests';

    /** GET endpoints that validate inline (no FormRequest): route name => rules. */
    private const INLINE = [
        'brand-memory.index' => ['kind' => 'nullable|string', 'product_id' => 'nullable|integer'],
        'truth-guard.check' => ['text' => 'required|string|max:5000', 'product_id' => 'nullable|integer'],
    ];

    private const PERIOD = ['from' => 'nullable|date', 'to' => 'nullable|date', 'channel' => 'nullable|string|in:direct,affiliate', 'per_page' => 'nullable|integer|min:1|max:100', 'date' => 'nullable|date'];

    public function handle(): int
    {
        $paths = [];
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/integration/v1')) {
                continue;
            }
            $uri = '/'.ltrim(substr($route->uri(), strlen('api/integration/v1')), '/');
            $uri = $uri === '/' ? '/' : rtrim($uri, '/');
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $paths[$uri][strtolower($method)] = $this->operation($route, strtolower($method), $uri);
            }
        }
        ksort($paths);
        $doc = ['openapi' => '3.0.3', 'info' => ['title' => '3alraf Growth integration API (n8n)', 'version' => '1.0.0', 'description' => 'Bearer token + HMAC signature. Aggregate data only; no price, refund, stock or budget endpoints. Every endpoint answers 403 feature_disabled when its Growth switch is OFF.'],
            'servers' => [['url' => '/api/integration/v1']], 'paths' => $paths,
            'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'], 'hmacSignature' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Signature']],
                'schemas' => [
                    'Envelope' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'additionalProperties' => true], 'meta' => ['type' => 'object', 'additionalProperties' => true]]],
                    'Error' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean', 'example' => false], 'message' => ['type' => 'string'], 'code' => ['type' => 'string', 'enum' => ['unauthenticated', 'growth_disabled', 'feature_disabled', 'forbidden_scope', 'ip_not_allowed', 'validation', 'too_many_requests']], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]]]],
                ]]];
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $this->option('stdout') ? $this->line($json) : file_put_contents(base_path('docs/api/integration-openapi.json'), $json);
        $this->info(count($paths).' paths documented');

        return self::SUCCESS;
    }

    private function operation(Route $route, string $method, string $uri): array
    {
        $scope = null;
        $feature = null;
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'integration.auth')) {
                $args = array_values(array_filter(explode(',', substr($m, strlen('integration.auth:')))));
                $scope = $args[0] ?? 'any';
                $feature = $args[1] ?? null;
            }
        }
        $name = preg_replace('/^integration\./', '', (string) $route->getName());
        $rules = self::INLINE[$name] ?? $this->formRequestRules($route);
        if ($rules === null && str_starts_with((string) $route->getActionName(), 'App\\Http\\Controllers\\Api\\Integration\\MetricsController')) {
            $rules = self::PERIOD;
        }
        $params = [];
        foreach ($route->parameterNames() as $p) {
            $params[] = ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        }
        $op = ['operationId' => $name, 'summary' => 'Scope: '.($scope ?? 'none').($feature ? '; feature: '.$feature : ''), 'x-scope' => $scope, 'x-feature' => $feature,
            'security' => [['bearerAuth' => [], 'hmacSignature' => []]]];
        if ($rules) {
            $schema = $this->schema($rules);
            if ($method === 'get') {
                foreach ($schema['properties'] as $name => $s) {
                    $params[] = ['name' => $name, 'in' => 'query', 'required' => in_array($name, $schema['required'] ?? [], true), 'schema' => $s];
                }
            } else {
                $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $schema]]];
            }
        }
        if ($params) {
            $op['parameters'] = $params;
        }
        $err = fn (string $d) => ['description' => $d, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
        $op['responses'] = ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]]]]
            + ($method === 'post' ? ['201' => ['description' => 'Created']] : [])
            + ['401' => $err('unauthenticated (bad token, signature or stale timestamp)'), '403' => $err('growth_disabled | feature_disabled | forbidden_scope | ip_not_allowed'), '422' => $err('validation'), '429' => $err('too_many_requests')];

        return $op;
    }

    private function formRequestRules(Route $route): ?array
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            $class = $action;
            $m = '__invoke';
        } else {
            [$class, $m] = explode('@', $action);
        }
        if (! class_exists($class) || ! method_exists($class, $m)) {
            return null;
        }
        foreach ((new \ReflectionMethod($class, $m))->getParameters() as $p) {
            $t = $p->getType();
            if ($t instanceof \ReflectionNamedType && ! $t->isBuiltin() && is_subclass_of($t->getName(), FormRequest::class)) {
                return (new ($t->getName()))->rules();
            }
        }

        return null;
    }

    /** @param array<string,mixed> $rules */
    private function schema(array $rules): array
    {
        $props = [];
        $req = [];
        foreach ($rules as $field => $rule) {
            $parts = is_string($rule) ? explode('|', $rule) : array_map(fn ($r) => is_object($r) ? (string) (method_exists($r, '__toString') ? $r : class_basename($r)) : (string) $r, (array) $rule);
            if (str_contains($field, '.')) {
                continue; // nested items are described by the parent's array type
            }
            $s = ['type' => 'string'];
            foreach ($parts as $p) {
                $p = (string) $p;
                $s = match (true) {
                    $p === 'integer' => ['type' => 'integer'] + $s,
                    $p === 'numeric' => ['type' => 'number'] + array_diff_key($s, ['type' => 1]),
                    $p === 'boolean' => ['type' => 'boolean'] + array_diff_key($s, ['type' => 1]),
                    $p === 'array' => ['type' => 'array', 'items' => ['type' => 'object']] + array_diff_key($s, ['type' => 1]),
                    $p === 'date' => $s + ['format' => 'date'],
                    str_starts_with($p, 'in:') => $s + ['enum' => array_map(fn ($v) => trim($v, '"'), explode(',', substr($p, 3)))],
                    str_starts_with($p, 'max:') => $s + ['maximum' => (float) substr($p, 4)],
                    str_starts_with($p, 'min:') => $s + ['minimum' => (float) substr($p, 4)],
                    default => $s,
                };
                if ($p === 'required') {
                    $req[] = $field;
                }
            }
            if (($s['type'] ?? '') === 'string' && isset($s['maximum'])) {
                $s['maxLength'] = (int) $s['maximum'];
                unset($s['maximum']);
            }
            if (($s['type'] ?? '') === 'string' && isset($s['minimum'])) {
                $s['minLength'] = (int) $s['minimum'];
                unset($s['minimum']);
            }
            $props[$field] = $s;
        }

        return ['type' => 'object', 'properties' => $props] + ($req ? ['required' => array_values(array_unique($req))] : []);
    }
}
