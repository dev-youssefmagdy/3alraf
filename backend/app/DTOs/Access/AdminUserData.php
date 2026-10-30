<?php

namespace App\DTOs\Access;

use App\Http\Requests\Access\AdminUserRequest;

final readonly class AdminUserData
{
    /** @param array<int,int> $roleIds */
    public function __construct(public string $name, public ?string $email, public string $phone, public ?string $password, public bool $isActive, public array $roleIds, public ?bool $otpExempt = null) {}

    public static function fromRequest(AdminUserRequest $r): self
    {
        $v = $r->validated();

        return new self($v['name'], $v['email'] ?? null, \App\Support\AdminPhone::digits($v['country'], $v['phone']), $v['password'] ?? null, (bool) ($v['is_active'] ?? false), array_map('intval', $v['roles'] ?? []), $r->has('otp_exempt') ? (bool) $v['otp_exempt'] : null);
    }
}
