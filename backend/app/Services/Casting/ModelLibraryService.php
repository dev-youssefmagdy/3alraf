<?php

namespace App\Services\Casting;

use App\Models\Marketer;
use App\Models\VirtualModel;
use App\Models\VirtualModelReferenceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ModelLibraryService
{
    public const KINDS = ['face', 'full_body', 'reference', 'pose'];

    public function __construct(private CastingStorage $storage, private PromptBuilder $prompts) {}

    private function plain(?string $s, int $n): ?string
    {
        return $s === null ? null : Str::limit(trim(strip_tags($s)), $n, '');
    }

    public function create(array $v, Marketer $by): VirtualModel
    {
        $name = $this->plain($v['name'], 120);
        $m = VirtualModel::create(['name' => $name, 'slug' => (Str::slug($name) ?: 'model').'-'.Str::lower(Str::random(6)), 'status' => VirtualModel::DRAFT, 'created_by' => $by->id,
            'identity_seed' => random_int(1, 2_000_000_000), 'identity_mode' => 'prompt_seed'] + $this->fields($v));
        $m->update(['identity_prompt' => $this->prompts->identity($m)]);

        return $m;
    }

    public function update(VirtualModel $m, array $v): VirtualModel
    {
        $m->update($this->fields($v) + (isset($v['name']) ? ['name' => $this->plain($v['name'], 120)] : []));
        $m->update(['identity_prompt' => $this->prompts->identity($m)]);

        return $m->refresh();
    }

    private function fields(array $v): array
    {
        return array_filter([
            'gender' => in_array($v['gender'] ?? null, ['female', 'male'], true) ? $v['gender'] : null, 'age_range' => $this->plain($v['age_range'] ?? null, 20),
            'appearance' => $this->plain($v['appearance'] ?? null, 600), 'hairstyle' => $this->plain($v['hairstyle'] ?? null, 200),
            'clothing_style' => $this->plain($v['clothing_style'] ?? null, 300), 'personality' => $this->plain($v['personality'] ?? null, 300), 'ad_role' => $this->plain($v['ad_role'] ?? null, 200),
        ], fn ($x) => $x !== null);
    }

    /** Copy keeps the identity (same prompt + seed) but starts as a draft with fresh reference copies; the original is untouched. */
    public function duplicate(VirtualModel $m, Marketer $by): VirtualModel
    {
        $copy = $m->replicate(['slug', 'approved_by', 'approved_at', 'source_candidate_id']);
        $copy->fill(['name' => Str::limit($m->name.' (copy)', 120, ''), 'slug' => (Str::slug($m->name) ?: 'model').'-'.Str::lower(Str::random(6)), 'status' => VirtualModel::DRAFT, 'created_by' => $by->id])->save();
        foreach ($m->references as $r) {
            $bytes = CastingStorage::disk()->get($r->path);
            if ($bytes) {
                $f = $this->storage->storeBytes('casting/models/'.$copy->id, $bytes);
                $copy->references()->create($f + ['kind' => $r->kind, 'source' => $r->source, 'disk' => CastingStorage::diskName(), 'is_primary' => $r->is_primary, 'sort_order' => $r->sort_order, 'created_by' => $by->id]);
            }
        }

        return $copy;
    }

    public function archive(VirtualModel $m): VirtualModel
    {
        $m->update(['status' => VirtualModel::ARCHIVED]);

        return $m;
    }

    public function restore(VirtualModel $m): VirtualModel
    {
        $m->update(['status' => $m->approved_at ? VirtualModel::APPROVED : VirtualModel::DRAFT]);

        return $m;
    }

    public function approve(VirtualModel $m, Marketer $by): VirtualModel
    {
        if (! $by->can('ai_casting.approve')) {
            abort(403);
        }
        if (! $m->references()->where('kind', 'face')->exists()) {
            throw ValidationException::withMessages(['model' => __('casting.errors.needs_face')]);
        }
        $m->update(['status' => VirtualModel::APPROVED, 'approved_by' => $by->id, 'approved_at' => now()]);

        return $m;
    }

    public function addReference(VirtualModel $m, UploadedFile $file, string $kind, Marketer $by): VirtualModelReferenceImage
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('casting.errors.bad_kind')]);
        }
        if ($m->references()->count() >= 30) {
            throw ValidationException::withMessages(['file' => __('casting.errors.too_many')]);
        }
        $f = $this->storage->storeUpload('casting/models/'.$m->id, $file);
        $first = ! $m->references()->where('kind', $kind)->exists();

        return $m->references()->create($f + ['kind' => $kind, 'source' => 'upload', 'disk' => CastingStorage::diskName(), 'is_primary' => $first, 'created_by' => $by->id]);
    }

    public function deleteReference(VirtualModelReferenceImage $r): void
    {
        $path = $r->path;
        $wasPrimary = $r->is_primary;
        $m = $r->virtualModel;
        $r->delete();
        $this->storage->delete($path);
        if ($wasPrimary && ($n = $m->references()->where('kind', $r->kind)->first())) {
            $n->update(['is_primary' => true]);
        }
    }
}
