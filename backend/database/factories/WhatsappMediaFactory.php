<?php

namespace Database\Factories;

use App\Models\WhatsappMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WhatsappMedia> */
class WhatsappMediaFactory extends Factory
{
    protected $model = WhatsappMedia::class;

    public function definition(): array
    {
        return ['disk' => 'private', 'mime' => 'image/jpeg', 'size' => 1024, 'status' => 'pending'];
    }
}
