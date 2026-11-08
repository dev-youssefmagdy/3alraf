<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only trail of every action on a suggestion (created, edited, submitted, approved, rejected, applied, failed, rolled_back, ...). */
class MarketingSuggestionActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['suggestion_id', 'action', 'actor_type', 'actor_id', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(MarketingSuggestion::class, 'suggestion_id');
    }

    public static function actorName(?string $type, ?int $id): string
    {
        return match ($type) {
            'admin' => (string) (Admin::query()->whereKey($id)->value('name') ?? '#'.$id),
            'token' => 'API: '.(string) (MarketingApiToken::query()->whereKey($id)->value('name') ?? '#'.$id),
            default => __('marketing.suggestions.system'),
        };
    }

    public function actorLabel(): string
    {
        return self::actorName($this->actor_type, $this->actor_id);
    }
}
