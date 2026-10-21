<?php

namespace App\DTOs;

use App\Http\Requests\Storefront\ReviewRequest;

final readonly class ReviewData
{
    public function __construct(public int $rating, public ?string $body) {}

    public static function fromRequest(ReviewRequest $r): self
    {
        return new self((int) $r->input('rating'), $r->filled('body') ? trim($r->input('body')) : null);
    }
}
