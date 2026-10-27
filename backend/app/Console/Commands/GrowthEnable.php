<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Growth\GrowthAccess;
use Illuminate\Console\Command;

class GrowthEnable extends Command
{
    protected $signature = 'growth:enable {tenant : slug} {--off : disable instead}';

    protected $description = 'Switch the `growth` feature flag for a tenant';

    public function handle(GrowthAccess $access): int
    {
        $t = Tenant::where('slug', $this->argument('tenant'))->first();
        if (! $t) {
            $this->error('Tenant not found');

            return self::FAILURE;
        }
        $access->set($t, ! $this->option('off'));
        $this->info('growth '.($this->option('off') ? 'disabled' : 'enabled').' for '.$t->slug);

        return self::SUCCESS;
    }
}
