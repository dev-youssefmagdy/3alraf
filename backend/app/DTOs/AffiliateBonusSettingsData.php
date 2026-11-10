<?php

namespace App\DTOs;

/** Affiliate sales bonus settings (threshold > 0, amount ≥ 0, decimal strings). */
final readonly class AffiliateBonusSettingsData
{
    public function __construct(public bool $enabled, public string $threshold, public string $amount) {}
}
