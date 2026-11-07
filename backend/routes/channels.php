<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Broadcast;

// One private channel per staff account (notification bell), authorised on guard `admin`.
Broadcast::channel('App.Models.Admin.{id}', fn (Admin $a, int $id) => $a->id === $id && $a->is_active, ['guards' => ['admin']]);

// WhatsApp connection status (single session) and WhatsApp chat inbox.
Broadcast::channel('whatsapp', fn (Admin $a) => $a->is_active && $a->can('whatsapp.view'), ['guards' => ['admin']]);
Broadcast::channel('whatsapp-chat', fn (Admin $a) => $a->is_active && $a->can('whatsapp.chat.view'), ['guards' => ['admin']]);
