<?php

namespace App\Repositories\Contracts;

use App\Models\MetaCampaignDraft;
use Illuminate\Database\Eloquent\Builder;

interface MetaCampaignDraftRepositoryInterface extends RepositoryInterface
{
    public function lockForUpdate(int $id): MetaCampaignDraft;

    /** Filters: status, token_id (own drafts), search, sort. */
    public function filtered(array $f): Builder;

    public function counts(): array;
}
