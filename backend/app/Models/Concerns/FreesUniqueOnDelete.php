<?php

namespace App\Models\Concerns;

/**
 * Soft-deleted rows must not keep a tenant-unique value (discount code, customer e-mail) reserved:
 * on soft delete the value is tombstoned ("value#del{id}"), so the unique index is free again and
 * validation (which ignores nothing special) simply sees the original value as available.
 * Use with SoftDeletes; set $freedOnDelete = ['code'] on the model.
 */
trait FreesUniqueOnDelete
{
    public static function bootFreesUniqueOnDelete(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }
            foreach ($model->freedOnDelete as $col) {
                $v = (string) $model->getRawOriginal($col);
                if ($v !== '' && ! str_contains($v, '#del')) {
                    $model->{$col} = mb_substr($v, 0, 200).'#del'.$model->getKey();
                    $model->saveQuietly();
                }
            }
        });
    }
}
