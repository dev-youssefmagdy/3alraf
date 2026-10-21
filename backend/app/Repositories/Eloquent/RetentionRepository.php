<?php

namespace App\Repositories\Eloquent;

use App\Models\Image;
use App\Models\QrCode;
use App\Repositories\Contracts\RetentionRepositoryInterface;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RetentionRepository implements RetentionRepositoryInterface
{
    public function eachExpired(string $modelClass, CarbonInterface $cutoff, int $chunk, Closure $callback): void
    {
        $modelClass::withoutGlobalScopes()->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff)
            ->chunkById($chunk, fn ($rows) => $rows->each($callback));
    }

    public function referenced(string $table, string $column, int $id): bool
    {
        return DB::table($table)->where($column, $id)->exists();
    }

    public function referencedAny(array $refs, int $id): bool
    {
        foreach ($refs as [$table, $column]) {
            if ($this->referenced($table, $column, $id)) {
                return true;
            }
        }

        return false;
    }

    public function detachImages(Model $owner): array
    {
        $paths = [];
        Image::query()->where('imageable_type', $owner->getMorphClass())->where('imageable_id', $owner->getKey())->get()->each(function (Image $i) use (&$paths) {
            $paths[] = $i->path;
            $paths[] = $i->thumb_path;
            $i->delete();
        });

        return array_values(array_filter($paths));
    }

    public function deleteWhere(string $table, array $where): int
    {
        return DB::table($table)->where($where)->delete();
    }

    public function detachQrCodes(string $ownerType, array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }
        $qrs = QrCode::withoutGlobalScopes()->where('owner_type', $ownerType)->whereIn('owner_id', $ownerIds)->get();
        $paths = $qrs->pluck('path')->filter()->all();
        QrCode::withoutGlobalScopes()->whereIn('id', $qrs->pluck('id'))->delete();

        return $paths;
    }

    public function ids(string $table, array $where, string $column = 'id'): array
    {
        return DB::table($table)->where($where)->pluck($column)->all();
    }
}
