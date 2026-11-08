<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service token of the Marketing internal API (/api/internal/marketing; the MCP server). Plain token "{prefix}.{secret}" is shown once;
 * only sha256(plain token) is stored. Distinct from IntegrationClient (n8n, HMAC). Created/revoked in the marketer panel (audited).
 */
class MarketingApiToken extends Model
{
    use Audited;

    protected array $auditExcept = ['token_hash', 'last_used_at'];

    protected $fillable = ['name', 'token_prefix', 'token_hash', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /** active | expired | revoked */
    public function state(): string
    {
        return $this->isRevoked() ? 'revoked' : ($this->isExpired() ? 'expired' : 'active');
    }
}
