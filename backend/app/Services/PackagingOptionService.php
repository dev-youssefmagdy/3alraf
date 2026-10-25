<?php

namespace App\Services;

use App\Repositories\Contracts\PackagingOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/** Packaging option CRUD; image stored/replaced through ImageUploadService (old file deleted on replace/remove). */
class PackagingOptionService extends CrudService
{
    public function __construct(PackagingOptionRepositoryInterface $repo, private ImageUploadService $images)
    {
        parent::__construct($repo);
    }

    protected function payload(object $dto, ?Model $model = null): array
    {
        $data = $dto->toArray();
        $image = $model?->image;
        if ($dto->removeImage && ! $dto->image) {
            $this->images->deletePath($image);
            $image = null;
        }
        if ($dto->image) {
            $image = $this->images->storeSingle($dto->image, 'packaging', $image);
        }
        $data['image'] = $image;

        return $data;
    }
}
