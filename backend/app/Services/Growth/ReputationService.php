<?php

namespace App\Services\Growth;

use App\Models\Interaction;
use App\Models\ReputationAlert;

/**
 * Reputation Radar: per product, complaint rate over the last 7 days vs the previous 28 days (one-sided z-test, minimum sample). A spike opens an alert that
 * pauses auto-reply for that product and groups similar complaints. Never deletes or argues with anything: it only informs a human.
 */
class ReputationService
{
    public const MIN_SAMPLE = 5;
    public const MIN_COMPLAINTS = 3;
    public const Z = 2.0;

    public function __construct(private ActionLogger $log) {}

    public static function isComplaint(Interaction $i): bool
    {
        return $i->sentiment === 'negative' || $i->intent === 'complaint';
    }

    /** @return int alerts opened or refreshed */
    public function detect(?\Carbon\CarbonInterface $now = null): int
    {
        $now ??= now();
        $recentFrom = $now->copy()->subDays(7);
        $baseFrom = $now->copy()->subDays(35);
        $n = 0;
        $recent = Interaction::whereNotNull('product_id')->where('received_at', '>=', $recentFrom)->get()->groupBy('product_id');
        foreach ($recent as $pid => $rows) {
            $total = $rows->count();
            $bad = $rows->filter(fn ($r) => self::isComplaint($r));
            if ($total < self::MIN_SAMPLE || $bad->count() < self::MIN_COMPLAINTS) {
                continue;
            }
            $base = Interaction::where('product_id', $pid)->where('received_at', '>=', $baseFrom)->where('received_at', '<', $recentFrom)->get();
            $p0 = $base->count() >= 10 ? $base->filter(fn ($r) => self::isComplaint($r))->count() / $base->count() : 0.1;
            $p0 = min(0.98, max(0.02, $p0));
            $p = $bad->count() / $total;
            $z = ($p - $p0) / sqrt($p0 * (1 - $p0) / $total);
            if ($p <= $p0 || $z < self::Z) {
                continue;
            }
            $topic = $bad->groupBy('category')->map->count()->sortDesc()->keys()->first();
            $groups = $bad->groupBy('text_hash')->map(fn ($g) => ['count' => $g->count(), 'text' => mb_substr($g->first()->text_masked, 0, 160)])->sortByDesc('count')->take(3)->values()->all();
            $alert = ReputationAlert::where('product_id', $pid)->where('status', 'open')->first();
            $data = ['topic' => $topic ?: null, 'rate' => round($p * 100, 2), 'baseline_rate' => round($p0 * 100, 2), 'z_score' => round($z, 2), 'sample' => $total, 'examples' => $groups, 'detected_on' => $now->toDateString()];
            if ($alert) {
                $alert->update($data);
            } else {
                $alert = ReputationAlert::create($data + ['product_id' => $pid, 'pause_auto_reply' => true, 'status' => 'open']);
                $this->log->log('reputation.alert', 'system', null, null, $alert, 'ok', ['product_id' => $pid, 'z' => $data['z_score']]);
            }
            $n++;
        }

        return $n;
    }

    public function resolve(int $id, ?int $adminId): ReputationAlert
    {
        $a = ReputationAlert::findOrFail($id);
        $a->update(['status' => 'resolved', 'pause_auto_reply' => false, 'resolved_at' => now()]);
        $this->log->log('reputation.resolved', 'admin', $adminId, null, $a);

        return $a;
    }

    public function open()
    {
        return ReputationAlert::where('status', 'open')->orderByDesc('z_score')->get();
    }
}
