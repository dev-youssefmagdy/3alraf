<?php

use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use Illuminate\Support\Facades\Broadcast;

// One private channel per staff account; each is authorised on its own guard (a tenant admin can never join another admin's or tenant's channel).
Broadcast::channel('App.Models.TenantAdmin.{id}', fn (TenantAdmin $a, int $id) => $a->id === $id && $a->is_active, ['guards' => ['tenant']]);
Broadcast::channel('App.Models.CentralAdmin.{id}', fn (CentralAdmin $a, int $id) => $a->id === $id && $a->is_active, ['guards' => ['central']]);

// WhatsApp connection channels: central admins -> whatsapp.central; a tenant admin -> ONLY their own tenant's channel.
Broadcast::channel('whatsapp.central', fn (CentralAdmin $a) => $a->is_active && $a->can('whatsapp.view'), ['guards' => ['central']]);
Broadcast::channel('whatsapp.tenant.{tenantId}', fn (TenantAdmin $a, int $tenantId) => $a->is_active && (int) $a->tenant_id === $tenantId && $a->can('whatsapp.view'), ['guards' => ['tenant']]);
