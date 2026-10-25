<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * Explicit COD shipping-prepay mode for a tenant (the real default, no setting, is UPFRONT = true).
 * Legacy tests that model "carrier collects the full total" call codUpfront($tenant, false).
 */
function codUpfront(\App\Models\Tenant $tenant, bool $on): \App\Models\Tenant
{
    $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['cod_collect_shipping_upfront' => $on])]);

    return $tenant->refresh();
}

/** Unique fake proof image per call (identical bytes would be rejected as a reused proof). Offline checkouts require one. */
function testProof(): \Illuminate\Http\UploadedFile
{
    static $n = 0;
    $n++;

    return \Illuminate\Http\UploadedFile::fake()->image("proof{$n}.png", 50 + $n, 20);
}
