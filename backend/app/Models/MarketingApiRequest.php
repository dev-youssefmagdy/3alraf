<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Call log of the Marketing internal API: token, method, route name, status, duration. No body, no IP, no PII. Pruned by marketing:prune-requests. */
class MarketingApiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['token_id', 'method', 'route', 'status', 'duration_ms', 'created_at'];

    protected function casts(): array
    {
        return ['status' => 'int', 'duration_ms' => 'int', 'created_at' => 'datetime'];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(MarketingApiToken::class, 'token_id');
    }
}
