<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** An external caller (n8n) of the integration API. Token stored hashed, HMAC secret encrypted; never logged. */
class IntegrationClient extends Model
{
    use Audited, BelongsToTenant;

    protected array $auditExcept = ['token_hash', 'hmac_secret', 'token_prefix'];

    protected $fillable = ['name', 'token_prefix', 'token_hash', 'hmac_secret', 'scopes', 'webhook_url', 'last_used_at', 'revoked_at'];

    protected $hidden = ['token_hash', 'hmac_secret'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'hmac_secret' => 'encrypted', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
