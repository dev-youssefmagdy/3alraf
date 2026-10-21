<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class StaticPageDetailResource extends StaticPageResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['content' => $this->content];
    }
}
