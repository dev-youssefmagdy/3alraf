<?php

namespace App\DTOs;

use App\Http\Requests\Storefront\AffiliateRegisterRequest;

final readonly class AffiliateRegisterData
{
    public function __construct(public string $name, public string $email, public ?string $phone, public string $password) {}

    public static function fromRequest(AffiliateRegisterRequest $r): self
    {
        return new self($r->input('name'), strtolower(trim($r->input('email'))), $r->input('phone') ?: null, $r->input('password'));
    }
}
