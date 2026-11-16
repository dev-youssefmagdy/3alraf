<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Catalog drafts (`is_draft`): saved with only a name, completed later. A draft is never active, whatever path saves it
 * (form, bulk activate, marketing suggestions...), so it can never reach the storefront by accident.
 */
trait HasDraftState
{
    public static function bootHasDraftState(): void
    {
        static::saving(function ($model) {
            if ($model->is_draft && array_key_exists('is_active', $model->getAttributes())) {
                $model->is_active = false;
            }
        });
    }

    public function initializeHasDraftState(): void
    {
        $this->mergeFillable(['is_draft']);
        $this->mergeCasts(['is_draft' => 'bool']);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where($this->qualifyColumn('is_draft'), false);
    }

    public function scopeDrafts(Builder $q): Builder
    {
        return $q->where($this->qualifyColumn('is_draft'), true);
    }
}
