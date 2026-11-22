<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelGenerationHistory extends Model
{
    protected $table = 'model_generation_history';

    protected $fillable = ['virtual_model_id', 'casting_candidate_id', 'product_id', 'live_product_id', 'kind', 'provider', 'status', 'prompt', 'seed', 'params', 'attempts', 'job_token',
        'disk', 'output_path', 'social_media_asset_id', 'error', 'duration_ms', 'created_by'];

    protected $hidden = ['disk', 'output_path', 'job_token'];

    protected function casts(): array
    {
        return ['params' => 'array', 'seed' => 'int', 'attempts' => 'int'];
    }

    public function virtualModel(): BelongsTo
    {
        return $this->belongsTo(VirtualModel::class);
    }

    public function liveProduct(): BelongsTo
    {
        return $this->belongsTo(CastingLiveProduct::class);
    }

    public function subject(): \App\Services\Casting\Subject
    {
        return new \App\Services\Casting\Subject($this->product, $this->liveProduct);
    }

    public function productLabel(): string
    {
        return (string) ($this->product?->name ?? $this->liveProduct?->displayName() ?? '');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
