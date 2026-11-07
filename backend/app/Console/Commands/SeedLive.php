<?php

namespace App\Console\Commands;

use Database\Seeders\LiveSeeder;
use Database\Seeders\Support\DemoDataPurger;
use Illuminate\Console\Command;

/** Live data only: removes the fake rows left by seed:dev, then seeds languages, permissions, countries, cities and the available gift products without stock. */
class SeedLive extends Command
{
    protected $signature = 'seed:live {--force : do not ask for confirmation (required when not interactive)} {--no-purge : keep any existing demo data, only add the live data}';

    protected $description = 'Remove demo data and seed live data only: countries, cities, available products without stock';

    public function handle(DemoDataPurger $purger): int
    {
        if (! $this->option('no-purge')) {
            $found = $purger->run(false);
            if ($found) {
                $this->warn('Demo data found:');
                $this->table(['Demo data', 'Rows'], collect($found)->map(fn ($n, $label) => [$label, $n])->values()->all());
                if (! $this->option('force') && ! ($this->input->isInteractive() && $this->confirm('Delete it permanently?'))) {
                    $this->error('Aborted: nothing was changed (use --force to delete without asking, or --no-purge to keep it).');

                    return self::FAILURE;
                }
                $purger->run(true);
                $this->info('Demo data removed.');
            } else {
                $this->line('No demo data found.');
            }
        }

        $this->call('db:seed', ['--class' => LiveSeeder::class, '--force' => true]);

        $this->info('Live data ready.');

        return self::SUCCESS;
    }
}
