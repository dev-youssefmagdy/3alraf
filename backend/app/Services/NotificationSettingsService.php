<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Support\CustomerMailPolicy;

/** tenants.settings.notifications = {placed,status,payment,return: bool} — which customer e-mails the store sends (default all on). */
class NotificationSettingsService
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function get(Tenant $tenant): array
    {
        $saved = (array) (($tenant->settings ?? [])['notifications'] ?? []);

        return collect(CustomerMailPolicy::GROUPS)->mapWithKeys(fn ($g) => [$g => (bool) ($saved[$g] ?? true)])->all();
    }

    public function update(Tenant $tenant, array $flags): array
    {
        $clean = collect(CustomerMailPolicy::GROUPS)->mapWithKeys(fn ($g) => [$g => (bool) ($flags[$g] ?? false)])->all();
        $this->tenants->update($tenant, ['settings' => array_merge($tenant->settings ?? [], ['notifications' => $clean])]);

        return $clean;
    }
}
