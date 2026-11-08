<?php

namespace App\Services\Marketing\Suggestions;

use App\Models\Admin;
use App\Models\MarketingApiToken;

/** Who acts on a suggestion: a panel admin, an API token (MCP / scheduler) or the system (scheduled expiry). */
final readonly class SuggestionActor
{
    public function __construct(public string $type, public ?int $id, public ?Admin $admin = null, public ?MarketingApiToken $token = null) {}

    public static function admin(Admin $a): self
    {
        return new self('admin', $a->id, $a);
    }

    public static function token(MarketingApiToken $t): self
    {
        return new self('token', $t->id, null, $t);
    }

    public static function system(): self
    {
        return new self('system', null);
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isToken(): bool
    {
        return $this->type === 'token';
    }

    public function can(string $permission): bool
    {
        return $this->admin !== null && $this->admin->can($permission);
    }
}
