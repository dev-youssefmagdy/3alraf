<?php

namespace App\Services;

use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExpenseService extends CrudService
{
    public function __construct(ExpenseRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    protected function payload(object $dto, ?Model $model = null): array
    {
        $data = $dto->toArray();
        if ($dto->attachment) {
            $data['attachment'] = $dto->attachment->store('tenants/'.current_tenant()?->id.'/expenses', config('media.private_disk'));
            $model?->attachment && Storage::disk(config('media.private_disk'))->delete($model->attachment); // new stored first
        } elseif ($dto->removeAttachment && $model?->attachment) {
            Storage::disk(config('media.private_disk'))->delete($model->attachment);
            $data['attachment'] = null;
        }
        if (! $model) {
            $data['created_by'] = auth('tenant')->id();
        }

        return $data;
    }

    protected function guardDelete(Model $model): void
    {
        $model->attachment && Storage::disk(config('media.private_disk'))->delete($model->attachment);
    }
}
