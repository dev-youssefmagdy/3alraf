<?php

namespace App\Console\Commands;

use App\DTOs\TenantData;
use App\Models\Language;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Creates a store: tenant + owner (tenant-owner role) + COD method + default branch + affiliate tiers. */
class CreateTenant extends Command
{
    protected $signature = 'tenants:create {slug : subdomain, e.g. egy} {name : store name}
        {--country= : ISO country code} {--currency=EGP : ISO currency code} {--symbol= : currency symbol (default = code)} {--rate=1 : rate to the base currency}
        {--locale=ar : default locale} {--locales=ar,en : comma separated} {--timezone=UTC}
        {--owner-email= : owner login (required)} {--owner-name= : owner display name} {--owner-password= : random when omitted (printed once)}';

    protected $description = 'Create a tenant with its owner account and default settings';

    public function handle(TenantService $tenants): int
    {
        $locales = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('locales')))));
        $input = [
            'slug' => strtolower((string) $this->argument('slug')), 'name' => $this->argument('name'), 'currency' => strtoupper((string) $this->option('currency')),
            'rate' => $this->option('rate'), 'locale' => $this->option('locale'), 'locales' => $locales, 'timezone' => $this->option('timezone'),
            'email' => $this->option('owner-email'), 'country' => $this->option('country'),
        ];
        $codes = Language::active()->pluck('code')->all();
        $v = Validator::make($input, [
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn(config('tenancy.reserved_subdomains', [])), Rule::unique('tenants', 'slug')],
            'name' => ['required', 'string', 'max:150'], 'currency' => ['required', 'string', 'size:3'], 'rate' => ['required', 'numeric', 'gt:0'],
            'locale' => ['required', Rule::in($codes)], 'locales' => ['required', 'array', 'min:1'], 'locales.*' => [Rule::in($codes)],
            'timezone' => ['required', 'timezone:all'], 'email' => ['required', 'email', 'max:190'], 'country' => ['nullable', 'string', 'size:2'],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $password = $this->option('owner-password') ?: Str::password(14, symbols: false);
        $name = $input['name'];
        $tenant = $tenants->create(new TenantData(
            $input['slug'], $name, $input['country'], $input['currency'], $this->option('symbol') ?: $input['currency'], (string) $input['rate'],
            $input['locale'], array_values(array_unique([...$locales, $input['locale']])), $input['timezone'], true,
            $this->option('owner-name') ?: $name.' Owner', $input['email'], $password,
        ));
        $this->info("Tenant {$tenant->slug} created ({$tenant->url()}).");
        $this->line("Owner: {$input['email']}".($this->option('owner-password') ? '' : "  password: $password  (shown once, change it after the first login)"));

        return self::SUCCESS;
    }
}
