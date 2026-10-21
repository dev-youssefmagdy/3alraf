<?php

namespace App\DTOs\Access;

use App\Http\Requests\Access\RoleRequest;

final readonly class RoleData
{
    /** @param array<int,string> $permissions */
    public function __construct(public string $name, public array $permissions) {}

    public static function fromRequest(RoleRequest $r): self
    {
        return new self((string) $r->validated('name'), (array) ($r->validated('permissions') ?? []));
    }
}
