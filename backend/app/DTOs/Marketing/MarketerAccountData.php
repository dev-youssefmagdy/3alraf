<?php

namespace App\DTOs\Marketing;

use App\Http\Requests\Marketer\MarketerAccountRequest;
use App\Support\AdminPhone;

final readonly class MarketerAccountData
{
    public function __construct(
        public string $name, public ?string $email, public string $phone, public ?string $password,
        public string $role, public bool $isActive, public bool $otpExempt,
    ) {}

    public static function fromRequest(MarketerAccountRequest $r): self
    {
        $v = $r->validated();

        return new self($v['name'], $v['email'] ?? null, AdminPhone::digits($v['country'], $v['phone']), $v['password'] ?? null,
            $v['role'], (bool) ($v['is_active'] ?? false), (bool) ($v['otp_exempt'] ?? false));
    }
}
