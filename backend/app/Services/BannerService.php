<?php

namespace App\Services;

use App\Repositories\Contracts\BannerRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class BannerService extends CrudService
{
    public function __construct(BannerRepositoryInterface $repo, private ImageUploadService $images)
    {
        parent::__construct($repo);
    }

    /** Per-locale image map {locale: path}; uploads replace, remove flags delete. */
    protected function payload(object $dto, ?Model $model = null): array
    {
        $data = $dto->toArray();
        $imgs = $model?->images ?? [];
        foreach ($dto->removeLocales as $loc) {
            $this->images->deletePath($imgs[$loc] ?? null);
            unset($imgs[$loc]);
        }
        foreach ($dto->images as $loc => $file) {
            $imgs[$loc] = $this->images->storeSingle($file, 'banners', $imgs[$loc] ?? null);
        }
        $data['images'] = $imgs;

        return $data;
    }

    protected function guardDelete(Model $model): void
    {
        foreach ($model->images ?? [] as $path) {
            $this->images->deletePath($path);
        }
    }
}
