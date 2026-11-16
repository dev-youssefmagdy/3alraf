<?php

namespace App\Services\Marketing\Suggestions;

use App\Models\Marketer;
use App\Models\MarketingApiToken;

/** Who acts on a suggestion / Meta draft: a marketer-panel account, an API token (MCP / scheduler) or the system (scheduled expiry). */
final readonly class SuggestionActor
{
    public function __construct(public string $type, public ?int $id, public ?Marketer $marketer = null, public ?MarketingApiToken $token = null) {}

    public static function marketer(Marketer $m): self
    {
        return new self('marketer', $m->id, $m);
    }

    public static function token(MarketingApiToken $t): self
    {
        return new self('token', $t->id, null, $t);
    }

    public static function system(): self
    {
        return new self('system', null);
    }

    public function isMarketer(): bool
    {
        return $this->type === 'marketer';
    }

    public function isToken(): bool
    {
        return $this->type === 'token';
    }

    public function can(string $permission): bool
    {
        return $this->marketer !== null && $this->marketer->can($permission);
    }
}
