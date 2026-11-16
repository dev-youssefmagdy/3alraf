<?php

namespace App\Services\Health;

use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Connection health of everything the shop depends on (Admin → Settings → System health). One method per check, each returns
 * ['status' => ok|warn|fail|off, 'message' => string, 'ms' => int]. `off` = not configured / not used here (not an error).
 * Checks never throw and never send anything to customers (no WhatsApp message, no e-mail, no Meta write).
 */
class HealthService
{
    public const CHECKS = ['database', 'cache', 'queue', 'storage', 'mail', 'whatsapp', 'reverb', 'meta', 'n8n', 'google', 'sentry'];

    public function run(string $key): array
    {
        abort_unless(in_array($key, self::CHECKS, true), 404);
        $t = hrtime(true);
        try {
            [$status, $message] = $this->{'check'.ucfirst($key)}();
        } catch (\Throwable $e) {
            [$status, $message] = ['fail', mb_substr($e->getMessage(), 0, 200)];
        }

        return ['key' => $key, 'status' => $status, 'message' => $message, 'ms' => (int) round((hrtime(true) - $t) / 1e6)];
    }

    private function checkDatabase(): array
    {
        DB::select('select 1');

        return ['ok', DB::connection()->getDatabaseName()];
    }

    private function checkCache(): array
    {
        $k = 'health:'.uniqid();
        Cache::put($k, '1', 10);
        $ok = Cache::get($k) === '1';
        Cache::forget($k);

        return $ok ? ['ok', config('cache.default')] : ['fail', __('health.msg.cache_rw')];
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');
        if ($driver === 'sync') {
            return ['warn', __('health.msg.queue_sync')];
        }
        $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return $failed > 0 ? ['warn', __('health.msg.queue_failed', ['n' => $failed, 'driver' => $driver])] : ['ok', $driver];
    }

    private function checkStorage(): array
    {
        $disk = Storage::disk(config('media.disk', 'public'));
        $f = 'health-'.uniqid().'.txt';
        $disk->put($f, 'ok');
        $ok = $disk->get($f) === 'ok';
        $disk->delete($f);

        return $ok ? ['ok', config('media.disk', 'public')] : ['fail', __('health.msg.storage_rw')];
    }

    private function checkMail(): array
    {
        $m = config('mail.default');
        if (in_array($m, ['log', 'array'], true)) {
            return ['warn', __('health.msg.mail_log', ['m' => $m])];
        }
        if ($m !== 'smtp') {
            return ['ok', $m];
        }
        $c = config('mail.mailers.smtp');
        $err = null;
        $s = @fsockopen(($c['scheme'] ?? null) === 'smtps' ? 'ssl://'.$c['host'] : $c['host'], (int) $c['port'], $no, $err, 5);
        if (! $s) {
            return ['fail', $c['host'].':'.$c['port'].' — '.($err ?: __('health.msg.unreachable'))];
        }
        $banner = trim((string) fgets($s, 256));
        fclose($s);

        return str_starts_with($banner, '220') ? ['ok', $c['host'].':'.$c['port']] : ['warn', mb_substr($banner, 0, 80)];
    }

    private function checkWhatsapp(): array
    {
        if (! config('services.whatsapp.url')) {
            return ['off', __('health.msg.not_configured')];
        }
        $s = app(WhatsappService::class)->refresh();   // reads Node status only; sends nothing

        return match ($s->status) {
            'connected' => ['ok', $s->phone ? '+'.$s->phone : 'connected'],
            'connecting', 'qr', 'pairing' => ['warn', __('health.msg.wa_pending', ['s' => $s->status])],
            default => ['fail', $s->last_error ?: __('health.msg.wa_down', ['s' => $s->status])],
        };
    }

    private function checkReverb(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['off', __('health.msg.reverb_off', ['d' => config('broadcasting.default')])];
        }
        $c = config('broadcasting.connections.reverb');
        if (! $c['key'] || ! $c['options']['host']) {
            return ['warn', __('health.msg.not_configured')];
        }
        $s = @fsockopen(($c['options']['scheme'] === 'https' ? 'ssl://' : '').$c['options']['host'], (int) $c['options']['port'], $no, $err, 4);
        if (! $s) {
            return ['fail', $c['options']['host'].':'.$c['options']['port'].' — '.($err ?: __('health.msg.unreachable'))];
        }
        fclose($s);

        return ['ok', $c['options']['host'].':'.$c['options']['port']];
    }

    private function checkMeta(): array
    {
        if (! config('meta.access_token') || ! config('meta.ad_account_id')) {
            return ['off', __('health.msg.not_configured')];
        }
        $r = Http::timeout(6)->get(rtrim((string) config('meta.graph_url'), '/').'/'.config('meta.graph_version').'/me', ['access_token' => config('meta.access_token')]);   // read-only
        if ($r->successful()) {
            return ['ok', (string) $r->json('name')];
        }

        return ['fail', (string) ($r->json('error.message') ?: 'HTTP '.$r->status())];
    }

    private function checkN8n(): array
    {
        $url = rtrim((string) (config('services.n8n.url') ?: 'http://127.0.0.1:5678'), '/').'/healthz';
        $r = Http::timeout(3)->get($url);

        return $r->successful() ? ['ok', $url] : ['fail', $url.' — HTTP '.$r->status()];
    }

    private function checkGoogle(): array
    {
        return config('services.google.client_id') && config('services.google.client_secret')
            ? ['ok', __('health.msg.configured')] : ['off', __('health.msg.not_configured')];
    }

    private function checkSentry(): array
    {
        return config('sentry.dsn') ? ['ok', __('health.msg.configured')] : ['off', __('health.msg.not_configured')];
    }
}
