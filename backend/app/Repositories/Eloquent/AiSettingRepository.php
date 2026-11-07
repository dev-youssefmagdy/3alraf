<?php

namespace App\Repositories\Eloquent;

use App\Models\AiSetting;
use App\Repositories\Contracts\AiSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class AiSettingRepository extends BaseRepository implements AiSettingRepositoryInterface
{
    protected string $model = AiSetting::class;

    public function query(): Builder
    {
        return AiSetting::query();
    }

    public function current(): AiSetting
    {
        return $this->query()->orderBy('id')->first() ?? new AiSetting;
    }

    public function save(array $data): AiSetting
    {
        $row = $this->current();
        $row->fill($data)->save();

        return $row;
    }
}
