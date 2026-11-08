<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ProductMediaOrderRequest;

final readonly class ProductMediaOrderData
{
    /** @param  array<int,array{type:string,id:int}>  $items  in display order */
    public function __construct(public array $items) {}

    public static function fromRequest(ProductMediaOrderRequest $request): self
    {
        return new self(array_map(function (string $key) {
            [$type, $id] = explode(':', $key);

            return ['type' => $type, 'id' => (int) $id];
        }, array_values($request->validated('order'))));
    }
}
