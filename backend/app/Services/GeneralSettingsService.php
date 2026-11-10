<?php

namespace App\Services;

use App\DTOs\GeneralSettingsData;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\Affiliates\AffiliateBonusService;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\Orders\CustomerCancellationService;
use Illuminate\Support\Facades\DB;

/** Settings → General: writes the shop profile keys of `settings` and the Gift Finder / customer-cancel switches (switch changes are audited). */
class GeneralSettingsService
{
    public function __construct(private GiftFinderSettings $giftFinder, private CustomerCancellationService $cancel, private AffiliateBonusService $bonus) {}

    /** @return array<string,mixed> current values for the form */
    public function current(): array
    {
        return [
            'name' => setting('name'), 'country' => setting('country'), 'currency_code' => setting('currency_code'), 'currency_symbol' => setting('currency_symbol'),
            'default_locale' => setting('default_locale'), 'locales' => (array) setting('locales', []), 'timezone' => setting('timezone'),
            'gift_finder_enabled' => $this->giftFinder->enabled(), 'customer_cancel_enabled' => $this->cancel->enabled(),
        ] + $this->bonus->settings(); // affiliate_bonus_enabled|threshold|amount + affiliate_bonus_started_at (read-only)
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
            if ($b = $d->affiliateBonus) {
                $old = $this->bonus->settings();
                $this->bonus->saveSettings($b->enabled, $b->threshold, $b->amount);
                Setting::flush();
                $new = $this->bonus->settings();
                foreach (['affiliate_bonus_enabled', 'affiliate_bonus_threshold', 'affiliate_bonus_amount'] as $k) {
                    if ($old[$k] !== $new[$k]) {
                        $changes[$k] = $new[$k];
                    }
                }
            }
        });
        Setting::flush();
        if ($changes) {
            activity('settings')->causedBy($actorId ? Admin::find($actorId) : null)->withProperties($changes)->log('general_settings.switches');
        }
    }
}
