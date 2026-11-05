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

    public function create(object $dto): Model
    {
        \App\Services\Erp\Accounting\PeriodGuard::assertOpen((string) $dto->date, 'expense_date');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($dto) {
            $e = parent::create($dto);
            event(new \App\Events\Erp\ExpenseSaved($e)); // ERP: Dr expense / Cr cash or payable

            return $e;
        });
    }

    public function update(Model $model, object $dto): Model
    {
        \App\Services\Erp\Accounting\PeriodGuard::assertOpen((string) $dto->date, 'expense_date');
        \App\Services\Erp\Accounting\PeriodGuard::assertOpen($model->expense_date->toDateString(), 'expense_date');
        \App\Services\Erp\Accounting\PeriodGuard::assertOpen(now()->toDateString(), 'expense_date'); // the old entry is reversed today (QA-BUG-101)

        return \Illuminate\Support\Facades\DB::transaction(function () use ($model, $dto) {
            $e = parent::update($model, $dto);
            event(new \App\Events\Erp\ExpenseSaved($e));

            return $e;
        });
    }

    public function delete(Model $model): void
    {
        // QA-BUG-101: the deletion reverses the expense journal today - refuse while today's period is closed (no document gone with its journal left)
        \App\Services\Erp\Accounting\PeriodGuard::assertOpen(now()->toDateString(), 'expense_date');

        \Illuminate\Support\Facades\DB::transaction(function () use ($model) {
            parent::delete($model);
            event(new \App\Events\Erp\ExpenseDeleted($model));
        });
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
