<?php

namespace App\Services\Growth;

use App\Models\Bundle;
use App\Models\BundleAbTest;
use App\Models\BundleAbVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bundle A/B (spec 4): variant B = alternative name / description / image of one bundle, traffic split by a stable hash of the visitor.
 * Counts exposures (bundle page views) and add-to-cart per variant; a winner is only suggested with the minimum sample and a clear difference
 * (two-proportion z-test >= 1.96). Nothing changes the bundle until an admin concludes the test.
 */
class BundleAbService
{
    public function __construct(private ActionLogger $log, private ExperimentService $experiments, private GrowthFeatures $features) {}

    public static function visitor(\Illuminate\Http\Request $r): string
    {
        return $r->hasSession() ? $r->session()->getId() : sha1($r->ip().'|'.$r->userAgent());
    }

    public function running(int $bundleId): ?BundleAbTest
    {
        return BundleAbTest::with('variants')->where('bundle_id', $bundleId)->where('status', 'running')->latest('id')->first();
    }

    public function start(int $bundleId, array $b, int $split, int $minSample, ?int $adminId): BundleAbTest
    {
        $bundle = Bundle::findOrFail($bundleId);
        if ($this->running($bundle->id)) {
            throw ValidationException::withMessages(['bundle_id' => __('growth_hub.bundles.ab_running')]);
        }
        $name = array_filter(array_map(fn ($v) => trim(strip_tags((string) $v)), (array) ($b['name'] ?? [])));
        $desc = array_filter(array_map(fn ($v) => trim(strip_tags((string) $v)), (array) ($b['description'] ?? [])));
        if (! $name && ! $desc && empty($b['image'])) {
            throw ValidationException::withMessages(['name' => __('growth_hub.bundles.ab_empty')]);
        }

        return DB::transaction(function () use ($bundle, $name, $desc, $b, $split, $minSample, $adminId) {
            $exp = $this->features->active('experiments')
                ? $this->experiments->create(['name' => 'Bundle A/B: '.$bundle->name, 'hypothesis' => 'Variant B lifts add-to-cart rate of the bundle page', 'metric' => 'bundle_cart_rate', 'status' => 'running', 'starts_on' => now()->toDateString()], $adminId) : null;
            $t = BundleAbTest::create(['bundle_id' => $bundle->id, 'experiment_id' => $exp?->id, 'split_b' => min(max($split, 5), 95), 'min_sample' => max($minSample, 20), 'created_by' => $adminId]);
            BundleAbVariant::create(['bundle_ab_test_id' => $t->id, 'label' => 'A']);
            BundleAbVariant::create(['bundle_ab_test_id' => $t->id, 'label' => 'B', 'name' => $name ?: null, 'description' => $desc ?: null, 'image' => $b['image'] ?? null]);
            $this->log->log('bundle_ab.started', 'admin', $adminId, null, $t);

            return $t->load('variants');
        });
    }

    /** Stable assignment: the same visitor always sees the same variant of a test. */
    public function assign(BundleAbTest $t, string $visitor): string
    {
        return (crc32($visitor.'|'.$t->id) % 100) < $t->split_b ? 'B' : 'A';
    }

    /** Storefront hook: returns the variant to show (and counts one exposure per visitor per test), or null when no test runs / feature off. */
    public function expose(int $bundleId, string $visitor): ?BundleAbVariant
    {
        if (! $this->features->active('bundle_ab') || ! ($t = $this->running($bundleId))) {
            return null;
        }
        $label = $this->assign($t, $visitor);
        $v = $t->variants->firstWhere('label', $label);
        if ($v && Cache::add('bab:exp:'.$t->id.':'.sha1($visitor), 1, now()->addDay())) {
            BundleAbVariant::whereKey($v->id)->increment('exposures');
        }

        return $v;
    }

    /** Add-to-cart of the bundle by a visitor: counts once per day per visitor for the variant they were assigned. */
    public function recordCart(int $bundleId, string $visitor): void
    {
        if (! $this->features->active('bundle_ab') || ! ($t = $this->running($bundleId))) {
            return;
        }
        $v = $t->variants->firstWhere('label', $this->assign($t, $visitor));
        if ($v && Cache::add('bab:cart:'.$t->id.':'.sha1($visitor), 1, now()->addDay())) {
            BundleAbVariant::whereKey($v->id)->increment('carts');
        }
    }

    public function results(BundleAbTest $t): array
    {
        $t->loadMissing('variants');
        $rows = $t->variants->map(fn ($v) => ['label' => $v->label, 'exposures' => $v->exposures, 'carts' => $v->carts, 'cart_rate' => $v->exposures > 0 ? round($v->carts / $v->exposures * 100, 2) : null])->all();
        [$a, $b] = [$t->variants->firstWhere('label', 'A'), $t->variants->firstWhere('label', 'B')];
        $rec = 'insufficient_data';
        if ($a && $b && $a->exposures >= $t->min_sample && $b->exposures >= $t->min_sample) {
            $p1 = $a->carts / $a->exposures;
            $p2 = $b->carts / $b->exposures;
            $p = ($a->carts + $b->carts) / ($a->exposures + $b->exposures);
            $se = sqrt($p * (1 - $p) * (1 / $a->exposures + 1 / $b->exposures));
            $z = $se > 0 ? ($p2 - $p1) / $se : 0;
            $rec = abs($z) >= 1.96 ? ($z > 0 ? 'winner_b' : 'winner_a') : 'inconclusive';
        }

        return ['variants' => $rows, 'min_sample' => $t->min_sample, 'recommendation' => $rec, 'metric_note' => 'add_to_cart_rate'];
    }

    /** Admin decision. Picking B copies its name/description/image to the bundle; A leaves the bundle untouched. */
    public function conclude(int $testId, string $winner, ?int $adminId): BundleAbTest
    {
        $t = BundleAbTest::with('variants', 'bundle')->findOrFail($testId);
        if ($t->status !== 'running' || ! in_array($winner, ['A', 'B'], true)) {
            throw ValidationException::withMessages(['state' => __('growth_hub.bundles.ab_bad_state')]);
        }
        DB::transaction(function () use ($t, $winner, $adminId) {
            $t->update(['status' => 'stopped', 'winner' => $winner]);
            $v = $t->variants->firstWhere('label', 'B');
            if ($winner === 'B' && $v && $t->bundle) {
                $b = $t->bundle;
                $b->name = array_replace($b->getTranslations('name'), (array) $v->name);
                $b->description = array_replace($b->getTranslations('description'), (array) $v->description);
                if ($v->image) {
                    $b->image = $v->image;
                }
                $b->save();
            }
            if ($t->experiment_id && $this->features->active('experiments') && ($e = \App\Models\GrowthExperiment::find($t->experiment_id))) {
                foreach ($this->results($t)['variants'] as $r) {
                    if ($r['cart_rate'] !== null) {
                        $this->experiments->addResult($e, ['metric' => 'cart_rate_'.$r['label'], 'value' => $r['cart_rate'], 'note' => $r['exposures'].' exposures'], 'admin', $adminId);
                    }
                }
                $this->experiments->update($e, ['name' => $e->name, 'status' => 'completed', 'outcome' => 'winner '.$winner, 'learnings' => 'bundle A/B concluded by admin'], $adminId);
            }
            $this->log->log('bundle_ab.concluded', 'admin', $adminId, null, $t, 'ok', ['winner' => $winner]);
        });

        return $t->refresh();
    }
}
