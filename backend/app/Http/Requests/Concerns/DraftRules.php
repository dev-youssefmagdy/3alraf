<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * "Save as draft" on catalog forms (submit button name `save_as_draft`). Allowed only on a new record or one that is
 * still a draft: a published record is hidden with "active" instead, so it never loses data other records rely on.
 */
trait DraftRules
{
    abstract protected function draftModel(): ?Model;

    public function isDraft(): bool
    {
        $model = $this->draftModel();

        return $this->boolean('save_as_draft') && (! $model?->exists || $model->is_draft);
    }

    /** For a draft, make the given fields optional (drops required / required_* / min on arrays); format rules stay. */
    protected function relaxForDraft(array $rules, array $fields): array
    {
        if (! $this->isDraft()) {
            return $rules;
        }
        foreach ($fields as $f) {
            if (! isset($rules[$f])) {
                continue;
            }
            $array = in_array('array', (array) $rules[$f], true);
            $kept = array_values(array_filter((array) $rules[$f], fn ($r) => ! (is_string($r)
                && (str_starts_with($r, 'required') || $r === 'nullable' || ($array && str_starts_with($r, 'min:'))))));
            $rules[$f] = ['nullable', ...$kept];
        }

        return $rules;
    }
}
