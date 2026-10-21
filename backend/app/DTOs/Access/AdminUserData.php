<?php

namespace App\DTOs\Access;

use App\Http\Requests\Access\AdminUserRequest;

final readonly class AdminUserData
{
    /** @param array<int,int> $roleIds */
    public function __construct(public string $name, public string $email, public ?string $password, public bool $isActive, public array $roleIds) {}

    public static function fromRequest(AdminUserRequest $r): self
    {
        $v = $r->validated();

        return new self($v['name'], $v['email'], $v['password'] ?? null, (bool) ($v['is_active'] ?? false), array_map('intval', $v['roles'] ?? []));
    }
}
