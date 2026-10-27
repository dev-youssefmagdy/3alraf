<?php

namespace App\Services\Growth;

use App\DTOs\PurchaseOrderData;
use App\Models\Branch;
use App\Models\GrowthApproval;
use App\Models\ImprovementTask;
use App\Models\ProductSupplyTerm;
use App\Models\SeasonalEvent;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TenantProduct;
use App\Services\PurchaseService;
use Illuminate\Validation\ValidationException;

/**
 * Capital & Inventory Decision Engine (spec 8). All numbers are computed here (never by AI). Scenarios (reorder / promo / bundle / wait) are
 * ranked by expected financial impact; assumptions are explicit and returned with each row. NOTHING is purchased: a reorder only becomes an
 * `inventory_reorder` approval, and approving it creates a DRAFT purchase order that a human still has to place and receive.
 */
class InventoryDecisionService
{
    public function __construct(private MetricsService $metrics, private GrowthConfig $config, private ApprovalService $approvals, private ActionLogger $log, private VocService $voc, private PurchaseService $purchases) {}

    private function cfg(string $k, mixed $d = null): mixed
    {
        return config('growth.capital.'.$k, $d);
    }

    /** @return array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>} */
    public function analyze(): array
    {
        $vel = $this->metrics->velocityRows(now(), (int) $this->cfg('horizon_days', 30))->keyBy('product_id');
        $floor = (float) $this->config->get('min_margin_percent', 15);
        $maxDisc = (float) $this->config->get('max_discount_percent', 30);
        $minCover = (float) $this->config->get('min_days_of_cover', 14);
        $maxCover = (float) $this->config->get('max_days_of_cover', 90);
        $cap = (float) $this->config->get('reorder_cash_cap', 0);
        $terms = ProductSupplyTerm::get()->keyBy('product_id');
        $age = StockMovement::where('type', 'purchase_in')->selectRaw('product_id, MAX(created_at) last_in')->groupBy('product_id')->pluck('last_in', 'product_id');
        $seasons = SeasonalEvent::whereDate('starts_on', '>=', now()->toDateString())->whereDate('starts_on', '<=', now()->addDays((int) $this->cfg('season_hold_days', 60))->toDateString())->get();
        $rows = [];
        foreach (TenantProduct::with('product.categories')->where('is_active', true)->get() as $tp) {
            $p = $tp->product;
            if (! $p || $p->trashed()) {
                continue;
            }
            $v = $vel[$tp->product_id] ?? ['on_hand' => 0, 'sold' => 0, 'daily_velocity' => 0, 'days_of_cover' => null];
            $price = (float) ($tp->sale_price ?: $tp->price);
            $cost = (float) $tp->cost;
            $unitMargin = $price - $cost;
            $marginPct = ($price > 0 && $cost > 0) ? $unitMargin / $price * 100 : null;
            $t = $terms[$tp->product_id] ?? null;
            $lead = (int) ($t?->lead_time_days ?? 14);
            $moq = max(1, (int) ($t?->moq ?? 1));
            $oh = (int) $v['on_hand'];
            $perDay = (float) $v['daily_velocity'];
            $cover = $v['days_of_cover'];
            $ageDays = isset($age[$tp->product_id]) ? (int) now()->diffInDays($age[$tp->product_id], true) : null;
            $cats = $p->categories->pluck('id')->all();
            $season = $seasons->first(fn ($e) => array_intersect((array) $e->category_ids, $cats));
            $expiring = $t?->shelf_life_days && $ageDays !== null && $ageDays >= $t->shelf_life_days * (float) $this->cfg('expiry_urgent_share', 0.7);
            $missing = array_values(array_filter([$cost <= 0 ? 'cost' : null, $price <= 0 ? 'price' : null, ! $t ? 'supply_terms' : null, $ageDays === null ? 'stock_age' : null]));
            $slow = $oh > 0 && ($perDay <= 0 || ($cover !== null && $cover > $maxCover));
            $row = ['product_id' => $tp->product_id, 'name' => $p->getTranslation('name', app()->getLocale(), true), 'on_hand' => $oh, 'sold_30d' => (int) $v['sold'], 'velocity' => $perDay, 'days_of_cover' => $cover,
                'age_days' => $ageDays, 'lead_time_days' => $lead, 'moq' => $moq, 'unit_cost' => $cost, 'price' => $price, 'margin_percent' => $marginPct === null ? null : round($marginPct, 1), 'contribution_30d' => round($unitMargin * (int) $v['sold'], 2),
                'cash_tied' => round($oh * $cost, 2), 'seasonal_event' => $season?->key, 'expiring' => (bool) $expiring, 'missing' => $missing, 'supplier_id' => $t?->supplier_id, 'scenarios' => [], 'action' => 'none', 'impact' => 0.0, 'reasons' => []];

            // reorder: only with velocity, a known cost and a margin above the floor
            $needsReorder = $perDay > 0 && $cover !== null && $cover < $lead + $minCover && $cost > 0 && $marginPct !== null && $marginPct >= $floor;
            if ($needsReorder) {
                $qty = max($moq, (int) ceil($perDay * ($lead + $minCover)) - $oh);
                $blocked = null;
                if ($cap > 0 && $qty * $cost > $cap) {
                    $fit = (int) floor($cap / $cost);
                    $fit >= $moq ? $qty = $fit : $blocked = 'cap';
                }
                $stockoutDays = max(0.0, $lead - $cover);
                $impact = round($stockoutDays * $perDay * $unitMargin, 2); // margin that would be lost while waiting for delivery
                $row['scenarios']['reorder'] = ['qty' => $qty, 'cash' => round($qty * $cost, 2), 'impact' => $impact, 'blocked' => $blocked, 'basis' => 'lost_margin_during_lead_time'];
            }
            // promo / clearance: slow stock, discount bounded by margin floor and max discount; uplift is an ASSUMPTION
            if ($slow && $price > 0 && $cost > 0) {
                $d = max(0.0, min($maxDisc, $marginPct - $floor));
                if ($d >= 1) {
                    $units = $perDay > 0 ? min($oh, (int) round($perDay * (float) $this->cfg('promo_uplift', 1.5) * (float) $this->cfg('horizon_days', 30))) : min($oh, (int) ceil($oh * (float) $this->cfg('promo_baseline_sell_through', 0.1)));
                    $row['scenarios']['promo'] = ['discount_percent' => round($d, 1), 'units' => $units, 'cash_released' => round($units * $cost, 2), 'impact' => round($units * ($price * (1 - $d / 100) - $cost), 2),
                        'assumption' => ['uplift' => (float) $this->cfg('promo_uplift', 1.5), 'baseline_sell_through' => (float) $this->cfg('promo_baseline_sell_through', 0.1)]];
                }
                $row['scenarios']['bundle'] = ['note' => 'use_bundle_engine', 'cash_tied' => $row['cash_tied'], 'impact' => 0.0];
            }
            $row['scenarios']['wait'] = ['cash_tied' => $row['cash_tied'], 'impact' => 0.0];

            [$row['action'], $row['reasons']] = $this->decide($row, $slow, $needsReorder, (bool) $season, (bool) $expiring);
            $row['impact'] = (float) ($row['scenarios'][$row['action']]['impact'] ?? 0);
            $rows[] = $row;
        }
        $rows = $this->classify($rows);
        usort($rows, fn ($a, $b) => $b['impact'] <=> $a['impact']);
        $slowValue = array_sum(array_map(fn ($r) => ($r['sold_30d'] === 0 && $r['on_hand'] > 0) ? $r['cash_tied'] : 0, $rows));

        return ['rows' => $rows, 'summary' => ['slow_moving_value' => round($slowValue, 2), 'inventory' => $this->metrics->inventory(now()), 'cash_tied' => round(array_sum(array_column($rows, 'cash_tied')), 2)]];
    }

    private function decide(array $r, bool $slow, bool $needsReorder, bool $season, bool $expiring): array
    {
        $why = [];
        if ($slow) {
            if ($expiring) {
                return [isset($r['scenarios']['promo']) ? 'promo' : 'wait', ['expiry_urgent']];
            }
            if ($season) {
                return ['wait', ['season_hold']]; // do not clear stock right before its own season
            }

            return [isset($r['scenarios']['promo']) ? 'promo' : 'bundle', ['slow_stock']];
        }
        if ($needsReorder) {
            if (($r['scenarios']['reorder']['blocked'] ?? null) === 'cap') {
                return ['wait', ['cash_cap']];
            }

            return ['reorder', ['low_cover']];
        }
        if ($r['sold_30d'] === 0 && $r['on_hand'] <= 0) {
            $why[] = 'no_history';
        }

        return ['wait', $why];
    }

    /** ABC by 30d contribution margin (A = top share), C includes zero/unknown contribution. */
    private function classify(array $rows): array
    {
        $total = array_sum(array_map(fn ($r) => max(0, $r['contribution_30d']), $rows));
        $sorted = $rows;
        usort($sorted, fn ($a, $b) => $b['contribution_30d'] <=> $a['contribution_30d']);
        $acc = 0.0;
        $cls = [];
        foreach ($sorted as $r) {
            $share = $total > 0 ? $acc / $total * 100 : 100;
            $cls[$r['product_id']] = ($r['contribution_30d'] <= 0 || $total <= 0) ? 'C' : ($share < (float) $this->cfg('abc_a_share', 80) ? 'A' : ($share < (float) $this->cfg('abc_b_share', 95) ? 'B' : 'C'));
            $acc += max(0, $r['contribution_30d']);
        }

        return array_map(fn ($r) => $r + ['abc' => $cls[$r['product_id']] ?? 'C'], $rows);
    }

    /** Reorder recommendation -> approval (dedupe: one pending per product). Never a purchase. */
    public function proposeReorder(int $productId, ?int $branchId, ?int $supplierId, ?int $adminId): GrowthApproval
    {
        $row = collect($this->analyze()['rows'])->firstWhere('product_id', $productId);
        $sc = $row['scenarios']['reorder'] ?? null;
        if (! $sc || $sc['blocked']) {
            throw ValidationException::withMessages(['product_id' => __('growth_capital.inv.not_recommended')]);
        }
        $supplierId ??= $row['supplier_id'];
        if (! $supplierId || ! Supplier::whereKey($supplierId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => __('growth_capital.inv.need_supplier')]);
        }
        $branchId ??= Branch::query()->value('id');
        if (GrowthApproval::where('type', 'inventory_reorder')->where('status', GrowthApproval::PENDING)->where('payload->product_id', $productId)->exists()) {
            throw ValidationException::withMessages(['product_id' => __('growth_capital.inv.already_pending')]);
        }
        $a = $this->approvals->request('inventory_reorder', __('growth_capital.inv.approval_title', ['product' => $row['name'], 'qty' => $sc['qty']]),
            ['product_id' => $productId, 'qty' => $sc['qty'], 'supplier_id' => $supplierId, 'branch_id' => $branchId, 'unit_cost' => $row['unit_cost'], 'cash' => $sc['cash'], 'days_of_cover' => $row['days_of_cover'], 'lead_time_days' => $row['lead_time_days']], null, $adminId ? 'admin:'.$adminId : 'system');
        $this->log->log('inventory.reorder_proposed', 'admin', $adminId, null, $a, 'ok', ['product_id' => $productId, 'qty' => $sc['qty']]);

        return $a;
    }

    /** Approval handler: re-checks cost, supplier and cash cap, then creates a DRAFT purchase order only. */
    public function materialize(GrowthApproval $a): array
    {
        $p = $a->payload;
        $tp = TenantProduct::where('product_id', (int) $p['product_id'])->where('is_active', true)->first();
        $qty = (int) $p['qty'];
        $cap = (float) $this->config->get('reorder_cash_cap', 0);
        if (! $tp || (float) $tp->cost <= 0 || $qty < 1) {
            throw ValidationException::withMessages(['state' => __('growth_capital.inv.recheck_failed')]);
        }
        if ($cap > 0 && $qty * (float) $tp->cost > $cap) {
            throw ValidationException::withMessages(['state' => __('growth_capital.inv.over_cap')]);
        }
        if (! Supplier::whereKey((int) $p['supplier_id'])->where('is_active', true)->exists() || ! Branch::whereKey((int) $p['branch_id'])->exists()) {
            throw ValidationException::withMessages(['state' => __('growth_capital.inv.need_supplier')]);
        }
        $po = $this->purchases->create(new PurchaseOrderData((int) $p['supplier_id'], (int) $p['branch_id'], now()->toDateString(), '0', '0', __('growth_capital.inv.po_note'),
            [['product_id' => (int) $p['product_id'], 'variant_id' => 0, 'quantity' => $qty, 'unit_cost' => (string) $tp->cost]], false));

        return ['purchase_order_id' => $po->id, 'status' => 'draft'];
    }

    public function saveTerms(int $productId, array $d, ?int $adminId): ProductSupplyTerm
    {
        TenantProduct::where('product_id', $productId)->firstOrFail();
        if (! empty($d['supplier_id'])) {
            Supplier::findOrFail((int) $d['supplier_id']);
        }
        $t = ProductSupplyTerm::updateOrCreate(['product_id' => $productId], ['supplier_id' => $d['supplier_id'] ?: null, 'lead_time_days' => (int) ($d['lead_time_days'] ?? 14), 'moq' => max(1, (int) ($d['moq'] ?? 1)), 'shelf_life_days' => ($d['shelf_life_days'] ?? '') !== '' ? (int) $d['shelf_life_days'] : null]);
        $this->log->log('inventory.terms_saved', 'admin', $adminId, null, $t);

        return $t;
    }

    /** Stock-to-Story: stock is fine, sales weak, repeated how-to-use / photo / size-colour questions -> content or bundle task (deduped per product + topic). @return int created */
    public function stockToStory(): int
    {
        $n = 0;
        $minQ = (int) $this->cfg('story_min_questions', 3);
        $maxSold = (int) $this->cfg('story_max_sold_30d', 3);
        $minCover = (float) $this->config->get('min_days_of_cover', 14);
        $vel = $this->metrics->velocityRows(now())->keyBy('product_id');
        foreach ($this->voc->recurring() as $r) {
            if (! in_array($r['category'], ['usage', 'photos', 'size_color'], true) || $r['count'] < $minQ) {
                continue;
            }
            $v = $vel[$r['product_id']] ?? null;
            $cover = $v['days_of_cover'] ?? null;
            if (! $v || $v['on_hand'] <= 0 || $v['sold'] > $maxSold || ($cover !== null && $cover < $minCover)) {
                continue;
            }
            $topic = 'story_'.$r['category'];
            if (ImprovementTask::where('product_id', $r['product_id'])->where('topic', $topic)->whereIn('status', ['open', 'doing'])->exists()) {
                continue;
            }
            $name = \App\Models\Product::whereKey($r['product_id'])->first()?->getTranslation('name', app()->getLocale(), true) ?? '#'.$r['product_id'];
            ImprovementTask::create(['product_id' => $r['product_id'], 'topic' => $topic, 'rule' => 'stock_to_story', 'title' => mb_substr(__('growth_capital.story.'.$r['category'], ['product' => $name]), 0, 200), 'due_on' => now()->addDays(14)->toDateString(), 'status' => 'open',
                'evidence' => [['source' => 'interactions', 'metric' => 'mentions_30d', 'value' => $r['count'], 'period' => '30d', 'topic' => $r['category']], ['source' => 'stock', 'metric' => 'on_hand', 'value' => $v['on_hand'], 'period' => 'now'], ['source' => 'orders', 'metric' => 'units_sold_30d', 'value' => $v['sold'], 'period' => '30d']], 'before_value' => $v['sold']]);
            $n++;
        }
        if ($n) {
            $this->log->log('inventory.stock_to_story', 'system', null, null, null, 'ok', ['created' => $n]);
        }

        return $n;
    }
}
