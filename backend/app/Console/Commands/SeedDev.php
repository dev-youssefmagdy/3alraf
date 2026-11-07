<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

/** Dev data: demo super admin + store owners, demo operations / orders / growth rows, countries, cities and the full gift catalog WITH opening stock. */
class SeedDev extends Command
{
    protected $signature = 'seed:dev {--force : run even when APP_ENV=production (creates accounts with the password "password")}';

    protected $description = 'Seed fake data for everything (demo accounts, demo operations, gift catalog with stock) for local / staging use';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('seed:dev creates demo accounts with the password "password". Refusing to run in production (use seed:live, or pass --force).');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Dev data ready: demo admins (admin@3alraf.test / owner@egy.test / owner@sa.test, password "password"), gift catalog with opening stock.');

        return self::SUCCESS;
    }
}
