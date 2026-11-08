<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned seasonal storefront palette (MCP.md §18). `tokens` = whitelisted Tailwind CSS variables (App\Services\Marketing\ThemeService::TOKENS)
 * rendered as a runtime `:root{...}` override by the storefront layout; compiled CSS is never edited. At most one row is_active.
 */
class MarketingTheme extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['key', 'name', 'version', 'tokens', 'notes', 'is_active', 'suggestion_id', 'previous_theme_id', 'created_by', 'activated_by', 'activated_at', 'deactivated_at'];

    protected function casts(): array
    {
        return ['tokens' => 'array', 'is_active' => 'bool', 'version' => 'int', 'activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_theme_id');
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(MarketingSuggestion::class, 'suggestion_id');
    }
}
