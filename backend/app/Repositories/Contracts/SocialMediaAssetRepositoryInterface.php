<?php

namespace App\Repositories\Contracts;

use App\Models\SocialMediaAsset;
use Illuminate\Database\Eloquent\Builder;

interface SocialMediaAssetRepositoryInterface extends RepositoryInterface
{
    public function lockForUpdate(int $id): SocialMediaAsset;

    /** Filters: product_id, kind, platform, campaign, status, tag, search, created_by_suggestion (bool), sort (newest|oldest). Archived hidden unless status=archived. */
    public function filtered(array $f): Builder;

    /** Distinct non-empty campaign names (filters). */
    public function campaigns(?int $productId = null): array;

    /** Counts per status (optionally for one product). */
    public function counts(?int $productId = null): array;
}
