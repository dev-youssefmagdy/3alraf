<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Reusable pose / camera prompt fragment (type = pose|camera). System presets are seeded; marketers may add their own. */
class ModelPosePreset extends Model
{
    protected $fillable = ['type', 'key', 'label', 'prompt_fragment', 'is_system', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['label' => 'array', 'is_system' => 'bool', 'is_active' => 'bool'];
    }

    public function labelFor(?string $locale = null): string
    {
        $l = (array) $this->label;

        return $l[$locale ?? app()->getLocale()] ?? $l['en'] ?? $this->key;
    }
}
