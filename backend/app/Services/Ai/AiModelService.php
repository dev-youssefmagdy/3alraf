<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiModelData;
use App\Models\AiModel;
use App\Repositories\Contracts\AiModelRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AiModelService
{
    public function __construct(private AiModelRepositoryInterface $models) {}

    public function create(AiModelData $d): AiModel
    {
        return DB::transaction(fn () => $this->afterSave($this->models->create($this->payload($d))));
    }

    public function update(AiModel $m, AiModelData $d): AiModel
    {
        return DB::transaction(fn () => $this->afterSave($this->models->update($m, $this->payload($d))));
    }

    public function delete(AiModel $m): void
    {
        $this->models->delete($m);
    }

    /** One default per kind; a default must be active. */
    private function afterSave(AiModel $m): AiModel
    {
        if ($m->is_default && ! $m->is_active) {
            $m->update(['is_default' => false]);
        }
        if ($m->is_default) {
            AiModel::where('kind', $m->kind->value)->where('id', '!=', $m->id)->update(['is_default' => false]);
        }

        return $m;
    }

    private function payload(AiModelData $d): array
    {
        return ['ai_provider_id' => $d->providerId, 'model_id' => $d->modelId, 'label' => $d->label, 'kind' => $d->kind, 'capabilities' => $d->capabilities,
            'input_cost' => $d->inputCost, 'output_cost' => $d->outputCost, 'max_output' => $d->maxOutput, 'is_active' => $d->isActive, 'is_default' => $d->isDefault];
    }
}
