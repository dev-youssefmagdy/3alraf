<?php

namespace App\Repositories\Eloquent;

use App\Models\MetaCampaignDraft;
use App\Repositories\Contracts\MetaCampaignDraftRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class MetaCampaignDraftRepository extends BaseRepository implements MetaCampaignDraftRepositoryInterface
{
    protected string $model = MetaCampaignDraft::class;

    public function lockForUpdate(int $id): MetaCampaignDraft
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function filtered(array $f): Builder
    {
        $q = $this->query();
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        if (! empty($f['token_id'])) {
            $q->where('created_by_token_id', (int) $f['token_id']);
        }
        if (! empty($f['search'])) {
            $q->where('name', 'like', '%'.addcslashes(trim((string) $f['search']), '%_\\').'%');
        }

        return ($f['sort'] ?? 'newest') === 'oldest' ? $q->orderBy('id') : $q->orderByDesc('id');
    }

    public function counts(): array
    {
        return $this->query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c)->all();
    }
}
