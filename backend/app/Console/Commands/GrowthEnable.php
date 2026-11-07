<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthAccess;
use Illuminate\Console\Command;

class GrowthEnable extends Command
{
    protected $signature = 'growth:enable {--off : disable instead}';

    protected $description = 'Switch the `growth` master flag (same switch as Settings → General)';

    public function handle(GrowthAccess $access): int
    {
        $access->set(! $this->option('off'));
        $this->info('growth '.($this->option('off') ? 'disabled' : 'enabled'));

        return self::SUCCESS;
    }
}
