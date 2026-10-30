<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Auth\SuperAdminProvisioner;
use App\Support\AdminPhone;
use Illuminate\Console\Command;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'admin:provision-super {--tenant= : tenant slug (omit for the central super admin)} {--name=Super Admin} {--country=EG} {--phone=}';

    protected $description = 'Create (or re-key) the single super admin of the central platform or of one tenant';

    public function handle(SuperAdminProvisioner $p): int
    {
        $tenantId = null;
        if ($slug = $this->option('tenant')) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->error("Unknown tenant $slug");

                return self::FAILURE;
            }
            $tenantId = $tenant->id;
        }
        $digits = AdminPhone::digits((string) $this->option('country'), (string) ($this->option('phone') ?: $this->ask('Phone number')));
        if (! $digits) {
            $this->error('Invalid phone number for that country.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (min 8 chars)');
        if (strlen((string) $password) < 8) {
            $this->error('Password too short.');

            return self::FAILURE;
        }
        $a = $p->provision($tenantId, (string) $this->option('name'), $digits, $password);
        $this->info("Super admin #{$a->getKey()} ready (".AdminPhone::mask($digits).').');

        return self::SUCCESS;
    }
}
