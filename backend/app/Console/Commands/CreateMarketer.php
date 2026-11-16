<?php

namespace App\Console\Commands;

use App\Models\Marketer;
use App\Support\AdminPhone;
use App\Support\Marketing\MarketingPermissions;
use Illuminate\Console\Command;

/** Creates (or re-keys) a marketer panel account; --owner gives role `marketer-owner` (approve / apply / publish / tokens / accounts). */
class CreateMarketer extends Command
{
    protected $signature = 'marketer:create {--name=Marketer} {--country=EG} {--phone=} {--email=} {--owner : Give the marketer-owner role}';

    protected $description = 'Create or update a marketer panel account (guard marketer)';

    public function handle(): int
    {
        $digits = AdminPhone::digits((string) $this->option('country'), (string) ($this->option('phone') ?: $this->ask('Phone number')));
        if (! $digits) {
            $this->error('Invalid phone number for that country.');

            return self::FAILURE;
        }
        $password = (string) $this->secret('Password (min 8 chars)');
        if (strlen($password) < 8) {
            $this->error('Password too short.');

            return self::FAILURE;
        }

        $m = Marketer::query()->firstOrNew(['phone' => $digits]);
        $m->fill(['name' => (string) $this->option('name'), 'password' => $password, 'is_active' => true]);
        if ($this->option('email')) {
            $m->email = (string) $this->option('email');
        }
        $m->save();
        $m->syncRoles([$this->option('owner') ? MarketingPermissions::OWNER_ROLE : MarketingPermissions::ROLE]);

        $this->info("Marketer #{$m->getKey()} ready (".AdminPhone::mask($digits).', role '.$m->getRoleNames()->implode(', ').'). Login: /marketer/login');

        return self::SUCCESS;
    }
}
