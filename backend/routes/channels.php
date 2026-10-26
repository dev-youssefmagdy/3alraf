<?php

use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use Illuminate\Support\Facades\Broadcast;

// One private channel per staff account; each is authorised on its own guard (a tenant admin can never join another admin's or tenant's channel).
Broadcast::channel('App.Models.TenantAdmin.{id}', fn (TenantAdmin $a, int $id) => $a->id === $id && $a->is_active, ['guards' => ['tenant']]);
Broadcast::channel('App.Models.CentralAdmin.{id}', fn (CentralAdmin $a, int $id) => $a->id === $id && $a->is_active, ['guards' => ['central']]);
