<?php

namespace App\Services\Casting;

use App\Jobs\Casting\GenerateCandidatePortraitJob;
use App\Models\CastingCandidate;
use App\Models\CastingSession;
use App\Models\Marketer;
use App\Models\CastingLiveProduct;
use App\Models\Product;
use App\Models\VirtualModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Casting Director: product + audience analysis → 4-6 distinct Egyptian candidates → portraits (queued) → manual approval → official model.
 * Nothing becomes an official model without an explicit approve() by a marketer holding ai_casting.approve (checked in the controller route AND here).
 */
class CastingDirector
{
    public function __construct(private ProductBriefBuilder $brief, private CandidatePlanner $planner, private PromptBuilder $prompts, private ProviderManager $providers, private CastingStorage $storage) {}

    public function start(Product|CastingLiveProduct|Subject $product, int $count, ?Marketer $by, ?string $provider = null, ?string $notes = null): CastingSession
    {
        $provider = in_array($provider, $this->providers->selectable(), true) ? $provider : config('casting.provider');
        $x = Subject::of($product);
        $brief = $this->brief->build($x);

        // one running session per product: prevents double clicks from queueing the GPU twice
        $busy = CastingSession::query()->where('product_id', $x->productId())->where('live_product_id', $x->liveProductId())->where('status', 'generating')->where('created_at', '>', now()->subHour())->exists();
        if ($busy) {
            throw ValidationException::withMessages(['product_id' => __('casting.errors.session_busy')]);
        }

        $session = DB::transaction(function () use ($x, $count, $by, $provider, $notes, $brief) {
            $s = CastingSession::create(['product_id' => $x->productId(), 'live_product_id' => $x->liveProductId(), 'status' => 'generating', 'provider' => $provider, 'requested_count' => $count,
                'product_brief' => $brief, 'audience' => $brief['audience'], 'notes' => $notes ? Str::limit(strip_tags($notes), 1000, '') : null, 'created_by' => $by?->id]);
            foreach ($this->planner->plan($brief, $count) as $p) {
                unset($p['en_name']);
                $c = $s->candidates()->create($p + ['seed' => random_int(1, 2_000_000_000), 'status' => 'planned']);
                $c->update(['prompt' => $this->prompts->portrait($c)]);
            }

            return $s;
        });
        $session->candidates->each(fn ($c) => $this->queue($c));

        return $session->load('candidates');
    }

    /** (Re)queue one portrait. A fresh token invalidates any job still in flight for this candidate. */
    public function queue(CastingCandidate $c, bool $newSeed = false): CastingCandidate
    {
        $token = Str::random(32);
        $c->update(['job_token' => $token, 'status' => 'queued', 'error' => null, 'attempts' => 0] + ($newSeed ? ['seed' => random_int(1, 2_000_000_000)] : []));
        $c->session?->update(['status' => 'generating']);
        GenerateCandidatePortraitJob::dispatch($c->id, $token);

        return $c->refresh();
    }

    public function regenerate(CastingCandidate $c): CastingCandidate
    {
        $this->guardOpen($c);

        return $this->queue($c, true);
    }

    /** Edit the description; the prompt is rebuilt from the edited fields (portrait is regenerated only when asked). */
    public function edit(CastingCandidate $c, array $v, bool $regenerate): CastingCandidate
    {
        $this->guardOpen($c);
        $plain = fn ($x, $n) => $x === null ? null : Str::limit(trim(strip_tags((string) $x)), $n, '');
        $c->update(array_filter([
            'name' => $plain($v['name'] ?? null, 120), 'age_range' => $plain($v['age_range'] ?? null, 20), 'appearance' => $plain($v['appearance'] ?? null, 600),
            'hairstyle' => $plain($v['hairstyle'] ?? null, 200), 'clothing' => $plain($v['clothing'] ?? null, 300), 'personality' => $plain($v['personality'] ?? null, 300),
            'ad_role' => $plain($v['ad_role'] ?? null, 200),
        ], fn ($x) => $x !== null && $x !== ''));
        $c->update(['prompt' => $this->prompts->portrait($c)]);

        return $regenerate ? $this->queue($c, false) : $c;
    }

    public function reject(CastingCandidate $c): CastingCandidate
    {
        $this->guardOpen($c);
        $c->update(['status' => 'rejected', 'job_token' => null]);
        $c->session?->refreshStatus();

        return $c;
    }

    /** Approve = save as official virtual model (portrait becomes the primary face reference). Manual, one candidate at a time. */
    public function approve(CastingCandidate $c, Marketer $by, ?string $nameOverride = null): VirtualModel
    {
        if (! $by->can('ai_casting.approve')) {
            abort(403);
        }
        if ($c->status !== 'ready' || ! $c->portrait_path || $c->virtual_model_id) {
            throw ValidationException::withMessages(['candidate' => __('casting.errors.not_ready')]);
        }

        return DB::transaction(function () use ($c, $by, $nameOverride) {
            $c = CastingCandidate::query()->lockForUpdate()->findOrFail($c->id);
            if ($c->virtual_model_id) {
                throw ValidationException::withMessages(['candidate' => __('casting.errors.not_ready')]);
            }
            $name = $nameOverride ? Str::limit(strip_tags($nameOverride), 120, '') : $c->name;
            $model = VirtualModel::create([
                'name' => $name, 'slug' => $this->slug($name), 'gender' => $c->gender, 'age_range' => $c->age_range, 'appearance' => $c->appearance, 'hairstyle' => $c->hairstyle,
                'clothing_style' => $c->clothing, 'personality' => $c->personality, 'ad_role' => $c->ad_role, 'identity_prompt' => $this->prompts->identity($c),
                'identity_seed' => $c->seed, 'identity_mode' => $c->generation_meta['identity_mode'] ?? 'prompt_seed', 'status' => VirtualModel::APPROVED,
                'source_candidate_id' => $c->id, 'created_by' => $by->id, 'approved_by' => $by->id, 'approved_at' => now(),
                'meta' => ['suggested_poses' => $c->suggested_poses, 'suggested_locations' => $c->suggested_locations, 'rationale' => $c->rationale],
            ]);
            $bytes = (string) CastingStorage::disk()->get($c->portrait_path);
            $f = $this->storage->storeBytes('casting/models/'.$model->id, $bytes);
            $model->references()->create($f + ['virtual_model_id' => $model->id, 'kind' => 'face', 'source' => 'generated', 'disk' => CastingStorage::diskName(), 'is_primary' => true, 'created_by' => $by->id]);
            $c->update(['status' => 'approved', 'virtual_model_id' => $model->id]);
            $c->session?->update(['status' => 'completed']);
            activity('casting')->causedBy($by)->performedOn($model)->log('casting.model_approved');

            return $model;
        });
    }

    private function guardOpen(CastingCandidate $c): void
    {
        if ($c->status === 'approved' || $c->virtual_model_id) {
            throw ValidationException::withMessages(['candidate' => __('casting.errors.already_approved')]);
        }
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'model';

        return $base.'-'.Str::lower(Str::random(6));
    }
}
