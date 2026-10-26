<?php

namespace App\Events;

use App\Models\OrderReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** kind: requested | approved | rejected | refunded */
class OrderReturnUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public OrderReturn $return, public string $kind, public ?int $actorId = null)
    {
        $this->actorId ??= auth('tenant')->id();
    }
}
