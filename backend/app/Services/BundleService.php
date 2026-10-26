<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Repositories\Contracts\BundleRepositoryInterface;
use App\Services\Qr\QrCodeManager;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Bundle CRUD: components are synced by (product, variant); the image is stored/replaced through ImageUploadService. */
class BundleService extends CrudService
{
    public function __construct(BundleRepositoryInterface $repo, private ImageUploadService $images, private QrCodeManager $qr)
    {
        parent::__construct($repo);
    }

    public function create(object $dto): Model
    {
        return DB::transaction(function () use ($dto) {
            $bundle = parent::create($dto);
            $this->syncItems($bundle, $dto->items);
            DB::afterCommit(fn () => $this->qr->syncBundle($bundle));

            return $bundle;
        });
    }

    public function update(Model $model, object $dto): Model
    {
        return DB::transaction(function () use ($model, $dto) {
            parent::update($model, $dto);
            $this->syncItems($model, $dto->items);
            DB::afterCommit(fn () => $this->qr->syncBundle($model));

            return $model;
        });
    }

    public function delete(Model $model): void
    {
        parent::delete($model);
        $this->qr->deleteForBundle($model);
    }

    public function find(int|string $id): Model
    {
        return $this->repo->query()->with('items')->findOrFail($id);
    }

    protected function payload(object $dto, ?Model $model = null): array
    {
        $data = $dto->toArray();
        $data['slug'] = Slug::unique(Bundle::class, $model?->slug, $dto->name, $model?->id);
        $image = $model?->image;
        if ($dto->removeImage && ! $dto->image) {
            $this->images->deletePath($image);
            $image = null;
        }
        if ($dto->image) {
            $image = $this->images->storeSingle($dto->image, 'bundles', $image);
        }
        $data['image'] = $image;

        return $data;
    }

    /** Soft delete keeps the row (order lines reference it); the image stays so history/invoices can still show it until the purge. */
    private function syncItems(Model $bundle, array $items): void
    {
        $keep = [];
        foreach ($items as $i) {
            $row = BundleItem::firstOrNew(['bundle_id' => $bundle->id, 'product_id' => $i['product_id'], 'product_variant_id' => $i['variant_id']]);
            $row->fill(['quantity' => $i['quantity']])->save();
            $keep[] = $row->id;
        }
        $bundle->items()->whereNotIn('id', $keep)->delete();
    }
}
