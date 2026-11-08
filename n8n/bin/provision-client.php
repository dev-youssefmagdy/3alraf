<?php
/**
 * Creates (or rotates) the n8n integration client through the app's own IntegrationClientService — the same code path the
 * admin page (/admin/integration-clients) uses. Writes {"id","token","secret"} to the given file with mode 0600 and prints
 * NOTHING secret. Usage (run by n8n/provision-client.sh):
 *   php n8n/bin/provision-client.php <laravel_base_path> <out_json> [client name]
 * If a non-revoked client with that name exists it is rotated (old token/secret stop working immediately), otherwise created
 * with scope gift_finder:read.
 */
[$self, $base, $out] = array_pad($argv, 3, null);
$name = $argv[3] ?? 'n8n (gift finder)';
if (! $base || ! $out) {
    fwrite(STDERR, "usage: php provision-client.php <laravel_base_path> <out_json> [name]\n");
    exit(2);
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = $app->make(App\Services\Integration\IntegrationClientService::class);
$existing = App\Models\IntegrationClient::query()->where('name', $name)->whereNull('revoked_at')->first();
if ($existing) {
    [$token, $secret] = $svc->rotate($existing);
    $id = $existing->id;
    $action = 'rotated';
} else {
    [$client, $token, $secret] = $svc->create($name, ['gift_finder:read']);
    $id = $client->id;
    $action = 'created';
}
umask(0077);
file_put_contents($out, json_encode(['id' => $id, 'name' => $name, 'token' => $token, 'secret' => $secret]));
chmod($out, 0600);
echo "integration client #{$id} \"{$name}\" {$action}; credentials written to {$out} (0600)\n";
