<?php

namespace Database\Factories;

use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WhatsappConversation> */
class WhatsappConversationFactory extends Factory
{
    protected $model = WhatsappConversation::class;

    public function definition(): array
    {
        $phone = '2010'.fake()->unique()->numerify('########');

        return ['tenant_id' => null, 'jid' => $phone.'@s.whatsapp.net', 'phone' => $phone, 'name' => fake()->name(), 'unread_count' => 0];
    }
}
