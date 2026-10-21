<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\SupplierRequest;

final readonly class SupplierData
{
    public function __construct(public string $name, public ?string $contactName, public ?string $phone, public ?string $email, public ?string $address, public ?string $notes, public bool $isActive) {}

    public static function fromRequest(SupplierRequest $r): self
    {
        return new self($r->input('name'), $r->input('contact_name'), $r->input('phone'), $r->input('email'), $r->input('address'), $r->input('notes'), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'contact_name' => $this->contactName, 'phone' => $this->phone, 'email' => $this->email, 'address' => $this->address, 'notes' => $this->notes, 'is_active' => $this->isActive];
    }
}
