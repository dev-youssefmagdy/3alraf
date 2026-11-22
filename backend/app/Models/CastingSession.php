<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CastingSession extends Model
{
    protected $fillable = ['product_id', 'live_product_id', 'status', 'provider', 'requested_count', 'product_brief', 'audience', 'notes', 'error', 'created_by'];

    protected function casts(): array
    {
        return ['product_brief' => 'array', 'audience' => 'array', 'requested_count' => 'int'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function liveProduct(): BelongsTo
    {
        return $this->belongsTo(CastingLiveProduct::class);
    }

    public function productLabel(): string
    {
        return (string) ($this->product?->name ?? $this->liveProduct?->displayName() ?? '');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(CastingCandidate::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'created_by');
    }

    /** Derived from the candidates: generating while any is queued/generating, ready when none is, failed when every one failed. */
    public function refreshStatus(): void
    {
        $c = $this->candidates()->pluck('status');
        if ($c->isEmpty()) {
            return;
        }
        $busy = $c->contains(fn ($s) => in_array($s, ['planned', 'queued', 'generating'], true));
        $status = $busy ? 'generating' : ($c->every(fn ($s) => $s === 'failed') ? 'failed' : 'ready');
        if ($this->status !== 'completed' && $this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }
}
