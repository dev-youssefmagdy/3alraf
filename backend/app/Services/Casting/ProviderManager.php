<?php

namespace App\Services\Casting;

use App\Services\Casting\Contracts\ImageProvider;
use App\Services\Casting\Providers\ComfyUiProvider;
use App\Services\Casting\Providers\FakeProvider;

class ProviderManager
{
    public const NAMES = ['comfyui', 'fake'];

    public function make(?string $name = null): ImageProvider
    {
        return match ($name ?: config('casting.provider')) {
            'fake' => new FakeProvider,
            default => new ComfyUiProvider,
        };
    }

    /** Providers a marketer may pick: the fake one only outside production. */
    public function selectable(): array
    {
        return app()->isProduction() ? ['comfyui'] : self::NAMES;
    }
}
