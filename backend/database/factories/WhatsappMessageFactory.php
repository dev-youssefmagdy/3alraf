<?php

namespace Database\Factories;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WhatsappMessage> */
class WhatsappMessageFactory extends Factory
{
    protected $model = WhatsappMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => WhatsappConversation::factory(), 'wa_message_id' => strtoupper(fake()->unique()->bothify('3EB0????????????')),
            'direction' => 'in', 'type' => 'text', 'body' => fake()->sentence(), 'status' => 'delivered', 'wa_timestamp' => now(),
        ];
    }
}
