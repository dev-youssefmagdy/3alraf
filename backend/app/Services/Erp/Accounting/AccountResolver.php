<?php

namespace App\Services\Erp\Accounting;

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;

/** Resolves a system role key ("sales_revenue", "cogs", ...) to its account. Never hardcode account ids. */
class AccountResolver
{
    /** @var array<string,int> */
    private array $cache = [];

    public function id(string $roleKey): int
    {
        return $this->cache[$roleKey] ??= Account::where('role_key', $roleKey)->where('is_active', true)->value('id')
            ?? throw new AccountingException(__('erp.errors.role_missing', ['role' => $roleKey]));
    }

    /** Accepts an account id or a role key. */
    public function resolve(int|string $ref): int
    {
        return is_int($ref) || ctype_digit((string) $ref) ? (int) $ref : $this->id($ref);
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
