<?php

namespace App\Services;

use App\DTOs\GeneralSettingsData;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\Orders\CustomerCancellationService;
use Illuminate\Support\Facades\DB;

/** Settings → General: writes the shop profile keys of `settings` and the Gift Finder / customer-cancel switches (switch changes are audited). */
class GeneralSettingsService
{
    public function __construct(private GiftFinderSettings $giftFinder, private CustomerCancellationService $cancel) {}

    /** @return array<string,mixed> current values for the form */
    public function current(): array
    {
        return [
            'name' => setting('name'), 'country' => setting('country'), 'currency_code' => setting('currency_code'), 'currency_symbol' => setting('currency_symbol'),
            'default_locale' => setting('default_locale'), 'locales' => (array) setting('locales', []), 'timezone' => setting('timezone'),
            'gift_finder_enabled' => $this->giftFinder->enabled(), 'customer_cancel_enabled' => $this->cancel->enabled(),
        ];
    }

    public function update(GeneralSettingsData $d, ?int $actorId): void
    {
        $changes = [];
        DB::transaction(function () use ($d, &$changes) {
            Setting::putMany($d->profile());

            if ($this->giftFinder->enabled() !== $d->giftFinderEnabled) {
                $this->giftFinder->set($d->giftFinderEnabled);
                $changes['gift_finder.enabled'] = $d->giftFinderEnabled;
            }
            if ($this->cancel->enabled() !== $d->customerCancelEnabled) {
                $this->cancel->setEnabled($d->customerCancelEnabled);
                $changes['customer_cancel.enabled'] = $d->customerCancelEnabled;
            }
        });
        Setting::flush();
        if ($changes) {
            activity('settings')->causedBy($actorId ? Admin::find($actorId) : null)->withProperties($changes)->log('general_settings.switches');
        }
    }
}
