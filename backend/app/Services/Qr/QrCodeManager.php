<?php

namespace App\Services\Qr;

use App\Enums\AffiliateStatus;
use App\Jobs\SyncQrCodesJob;
use App\Models\Affiliate;
use App\Models\Bundle;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Repositories\Contracts\QrCodeRepositoryInterface;
use App\Services\ImageUploadService;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Keeps the stored QR PNGs (media disk) in step with the catalogue:
 * tenant product, variant (?variant=ID) and affiliate x product / variant (?utm_source=affiliate&aff=UUID).
 * Idempotent: a code is regenerated only when its URL hash changes (or $force); the replaced file is deleted.
 * Must run inside the owning tenant's context (TenantContext::run) - jobs / qr:rebuild do that.
 */
class QrCodeManager
{
    public function __construct(
        private QrCodeRepositoryInterface $codes,
        private QrCodeService $png,
    ) {}

    // ------------------------------------------------------------------ sync

    /** Product + variant codes of one listing (inline-friendly); affiliates too when asked. */
    public function syncProduct(TenantProduct $tp, bool $withAffiliates = false, bool $force = false): void
    {
        $tp->loadMissing('product');
        if ($tp->product?->trashed()) {
            return; // soft-deleted catalogue product: keep what exists
        }
        if (! $this->sellable($tp)) {
            $this->deleteFor($tp);

            return;
        }
        $tp->ensureVariantRows();
        $affiliates = $withAffiliates ? $this->approvedAffiliates() : collect();
        $this->reconcile($tp, $affiliates, $withAffiliates, $force);
    }

    /** All QR of one affiliate (approved) for every sellable listing; suspended / pending => removed. */
    public function syncAffiliate(Affiliate $a, bool $force = false): void
    {
        if ($a->status !== AffiliateStatus::APPROVED) {
            $this->deleteForAffiliate($a);

            return;
        }
        $keep = collect();
        foreach (TenantProduct::query()->where('is_active', true)->with('product')->get() as $tp) {
            if ($tp->product?->trashed() || ! $this->sellable($tp)) {
                continue;
            }
            $tp->ensureVariantRows();
            $keep = $keep->merge($this->reconcile($tp, collect([$a]), false, $force, $a->id));
        }
        // listings that are gone / disabled
        foreach ($this->codes->forAffiliate($a->id) as $qr) {
            if (! $keep->contains($qr->id)) {
                $this->remove($qr);
            }
        }
    }

    /** After a listing was enabled / edited: product + variant codes right after commit, affiliate fan-out on the queue. */
    public function listingSaved(TenantProduct $tp): void
    {
        DB::afterCommit(fn () => $this->syncProduct($tp));
        SyncQrCodesJob::dispatch(SyncQrCodesJob::LISTING, $tp->tenant_id, $tp->id)->afterCommit();
    }

    /** Affiliate approved / suspended / changed: queued (affiliate x all products x variants). */
    public function affiliateChanged(Affiliate $a): void
    {
        SyncQrCodesJob::dispatch(SyncQrCodesJob::AFFILIATE, $a->tenant_id, $a->id)->afterCommit();
    }

    /** Re-sync every tenant listing of a central product (slug / variants changed). Dispatches one job per listing. */
    public function queueCatalogProduct(int $productId): void
    {
        TenantProduct::withoutGlobalScopes()->where('product_id', $productId)->get(['id', 'tenant_id'])
            ->each(fn ($tp) => SyncQrCodesJob::dispatch(SyncQrCodesJob::LISTING, $tp->tenant_id, $tp->id)->afterCommit());
    }

    // ------------------------------------------------------------------ bundles

    /** Bundle code right after save (idempotent); a trashed bundle keeps what exists. */
    public function syncBundle(Bundle $bundle, bool $force = false): ?QrCode
    {
        if ($bundle->trashed()) {
            return null;
        }

        return $this->writeBundle($bundle, $this->codes->findFor($bundle, null), $force);
    }

    public function deleteForBundle(Bundle $bundle): void
    {
        $this->codes->query()->where('owner_type', Bundle::class)->where('owner_id', $bundle->id)->get()->each(fn ($q) => $this->remove($q));
    }

    /** Stored code of a bundle; generated on demand when missing (self-healing). */
    public function ensureBundle(Bundle $bundle): ?QrCode
    {
        if ($bundle->trashed() || (current_tenant() && $bundle->tenant_id !== current_tenant()->id)) {
            return null;
        }
        $qr = $this->codes->findFor($bundle, null);

        return $qr && ImageUploadService::disk()->exists($qr->path) ? $qr : $this->writeBundle($bundle, $qr);
    }

    public function bundleDownload(Bundle $bundle): ?array
    {
        $qr = $this->ensureBundle($bundle);
        $bytes = $qr ? $this->png($qr) : null;

        return $bytes === null ? null : [$bytes, 'qr-bundle-'.$bundle->slug.'.png'];
    }

    public function bundleCode(Bundle $bundle): ?QrCode
    {
        return $this->codes->findFor($bundle, null);
    }

    // ------------------------------------------------------------------ manual regenerate

    /** Force a fresh plain code for a listing (or one of its variant rows); false when it is not sellable. */
    public function regenerate(TenantProduct $tp, ?TenantProductVariant $tv = null): bool
    {
        $tp->loadMissing('product');
        if ($tp->product === null || $tp->product->trashed() || ! $this->sellable($tp)) {
            return false;
        }
        if ($tv) {
            $tv->loadMissing('variant');
            if ($tv->tenant_product_id !== $tp->id || ! $this->variantSellable($tv)) {
                return false;
            }
        }
        $owner = $tv ?? $tp;
        $this->write($owner, $tp->product->slug, $tv?->product_variant_id, null, $tp, $this->codes->findFor($owner, null), true);

        return true;
    }

    public function regenerateBundle(Bundle $bundle): bool
    {
        return $this->syncBundle($bundle, true) !== null;
    }

    // ------------------------------------------------------------------ delete

    /** Disabled / deleted listing (or variant): files + rows of product, variants and every affiliate code. */
    public function deleteFor(Model $owner): void
    {
        if ($owner instanceof TenantProductVariant) {
            $this->codes->query()->where('owner_type', TenantProductVariant::class)->where('owner_id', $owner->id)->get()->each(fn ($q) => $this->remove($q));

            return;
        }
        if ($owner instanceof TenantProduct) {
            $this->codes->forListing($owner)->each(fn ($q) => $this->remove($q));
        }
    }

    public function deleteForAffiliate(Affiliate $a): void
    {
        $this->codes->forAffiliate($a->id)->each(fn ($q) => $this->remove($q));
    }

    // ------------------------------------------------------------------ read / on demand

    /** Stored code for (listing[, variant row][, affiliate]); generated on demand when missing (self-healing). */
    public function ensure(TenantProduct $tp, ?TenantProductVariant $tv = null, ?Affiliate $affiliate = null): ?QrCode
    {
        if (current_tenant() && $tp->tenant_id !== current_tenant()->id) {
            return null; // never serve / generate another tenant's code
        }
        $owner = $tv ?? $tp;
        $qr = $this->codes->findFor($owner, $affiliate?->id);
        if ($qr && ImageUploadService::disk()->exists($qr->path)) {
            return $qr;
        }
        $tp->loadMissing('product');
        if ($tp->product === null || $tp->product->trashed() || ! $this->sellable($tp)) {
            return null;
        }
        if ($tv) {
            $tv->loadMissing('variant');
            if ($tv->tenant_product_id !== $tp->id || ! $this->variantSellable($tv)) {
                return null;
            }
        }
        if ($affiliate && $affiliate->status !== AffiliateStatus::APPROVED) {
            return null;
        }

        return $this->write($owner, $tp->product->slug, $tv?->product_variant_id, $affiliate, $tp);
    }

    /** One affiliate's stored codes keyed "{owner class}:{owner id}". */
    public function affiliateCodes(Affiliate $a): Collection
    {
        return $this->codes->forAffiliate($a->id)->keyBy(fn (QrCode $q) => $q->owner_type.':'.$q->owner_id);
    }

    /** Key into affiliateCodes() for a listing or variant row. */
    public function codeKey(Model $owner): string
    {
        return $owner::class.':'.$owner->getKey();
    }

    /** PNG bytes of an affiliate's code for a product slug [+ central variant id]; null when it does not exist / is not sellable. */
    public function affiliatePng(Affiliate $a, string $slug, ?int $variantId = null): ?string
    {
        $tp = TenantProduct::query()->whereHas('product', fn ($q) => $q->where('slug', $slug))->first();
        if (! $tp) {
            return null;
        }
        $tv = null;
        if ($variantId) {
            $tp->ensureVariantRows();
            $tv = TenantProductVariant::query()->where('tenant_product_id', $tp->id)->where('product_variant_id', $variantId)->first();
            if (! $tv) {
                return null;
            }
        }

        return ($qr = $this->ensure($tp, $tv, $a)) ? $this->png($qr) : null;
    }

    /** Admin download: PNG bytes + file name of the listing's plain code (or one variant row's). */
    public function tenantDownload(TenantProduct $tp, ?int $tenantVariantId = null): ?array
    {
        $tv = $tenantVariantId ? TenantProductVariant::query()->where('tenant_product_id', $tp->id)->find($tenantVariantId) : null;
        if ($tenantVariantId && ! $tv) {
            return null;
        }
        $qr = $this->ensure($tp, $tv);
        $bytes = $qr ? $this->png($qr) : null;
        if ($bytes === null) {
            return null;
        }

        return [$bytes, 'qr-'.$tp->product->slug.($tv ? '-variant-'.$tv->product_variant_id : '').'.png'];
    }

    /** Stored plain codes of a listing for the admin page, keyed "{owner class}:{owner id}". */
    public function listingCodes(TenantProduct $tp): Collection
    {
        return $this->codes->forListing($tp)->whereNull('affiliate_id')->keyBy(fn (QrCode $q) => $q->owner_type.':'.$q->owner_id);
    }

    public function png(QrCode $qr): ?string
    {
        $disk = ImageUploadService::disk();

        return $disk->exists($qr->path) ? $disk->get($qr->path) : null;
    }

    // ------------------------------------------------------------------ internals

    /**
     * Desired set for one listing; writes missing / changed, drops the rest of the scope.
     *
     * @param  Collection<int,Affiliate>  $affiliates
     * @param  bool  $pruneAffiliates  also remove affiliate codes that are no longer wanted (full sync)
     * @param  int|null  $onlyAffiliate  scope for syncAffiliate(): leave plain codes and other affiliates alone
     * @return Collection<int,int> ids of the codes kept
     */
    private function reconcile(TenantProduct $tp, Collection $affiliates, bool $pruneAffiliates, bool $force, ?int $onlyAffiliate = null): Collection
    {
        $slug = $tp->product->slug;
        $variants = $tp->variants()->with('variant')->get()->filter(fn ($tv) => $this->variantSellable($tv))->values();

        $want = [[$tp, null, null]];
        foreach ($variants as $tv) {
            $want[] = [$tv, $tv->product_variant_id, null];
        }
        foreach ($affiliates as $a) {
            $want[] = [$tp, null, $a];
            foreach ($variants as $tv) {
                $want[] = [$tv, $tv->product_variant_id, $a];
            }
        }

        $existing = $this->codes->forListing($tp)->keyBy(fn (QrCode $q) => $this->key($q->owner_type, $q->owner_id, $q->affiliate_id));
        $kept = collect();
        foreach ($want as [$owner, $variantId, $aff]) {
            if ($onlyAffiliate !== null && $aff?->id !== $onlyAffiliate) {
                continue;
            }
            $key = $this->key($owner::class, $owner->getKey(), $aff?->id);
            $qr = $this->write($owner, $slug, $variantId, $aff, $tp, $existing->get($key), $force);
            $kept->push($qr->id);
        }

        foreach ($existing as $qr) {
            if ($kept->contains($qr->id)) {
                continue;
            }
            $isAffiliateCode = $qr->affiliate_id !== null;
            $orphanVariant = $qr->owner_type === TenantProductVariant::class && ! $variants->pluck('id')->contains($qr->owner_id);
            $inScope = $onlyAffiliate !== null ? $qr->affiliate_id === $onlyAffiliate : ($isAffiliateCode ? ($pruneAffiliates || $orphanVariant) : true);
            if ($inScope) {
                $this->remove($qr);
            }
        }

        return $kept;
    }

    private function write(Model $owner, string $slug, ?int $variantId, ?Affiliate $aff, TenantProduct $tp, ?QrCode $current = null, bool $force = false): QrCode
    {
        $url = $this->url($tp->tenant ?? Tenant::findOrFail($tp->tenant_id), $slug, $variantId, $aff);
        $hash = sha1($url);
        $current ??= $this->codes->findFor($owner, $aff?->id);
        $disk = ImageUploadService::disk();
        if ($current && ! $force && $current->url_hash === $hash && $disk->exists($current->path)) {
            return $current;
        }

        $path = $this->path($tp->tenant_id, $owner, $hash, $force);
        $disk->put($path, $this->png->png($url));
        $old = $current?->path;
        $attrs = ['url' => $url, 'url_hash' => $hash, 'path' => $path];
        $qr = $current
            ? $this->codes->update($current, $attrs)
            : $this->codes->create($attrs + ['owner_type' => $owner::class, 'owner_id' => $owner->getKey(), 'affiliate_id' => $aff?->id, 'tenant_id' => $tp->tenant_id]);
        if ($old && $old !== $path) {
            $disk->delete($old); // replaced: the old (content-hashed) file is dead
        }

        return $qr;
    }

    private function writeBundle(Bundle $bundle, ?QrCode $current, bool $force = false): QrCode
    {
        $tenant = Tenant::findOrFail($bundle->tenant_id);
        $url = tenant_url($tenant, '/'.$tenant->default_locale.'/bundle/'.$bundle->slug);
        $hash = sha1($url);
        $disk = ImageUploadService::disk();
        if ($current && ! $force && $current->url_hash === $hash && $disk->exists($current->path)) {
            return $current;
        }
        $path = $this->path($bundle->tenant_id, $bundle, $hash, $force);
        $disk->put($path, $this->png->png($url));
        $old = $current?->path;
        $attrs = ['url' => $url, 'url_hash' => $hash, 'path' => $path];
        $qr = $current
            ? $this->codes->update($current, $attrs)
            : $this->codes->create($attrs + ['owner_type' => Bundle::class, 'owner_id' => $bundle->id, 'affiliate_id' => null, 'tenant_id' => $bundle->tenant_id]);
        if ($old && $old !== $path) {
            $disk->delete($old);
        }

        return $qr;
    }

    private function remove(QrCode $qr): void
    {
        ImageUploadService::disk()->delete($qr->path);
        $this->codes->delete($qr);
    }

    /** {tenant_url}/{default_locale}/product/{slug}[?variant=ID][&utm_source=affiliate&aff=UUID] */
    public function url(Tenant $tenant, string $slug, ?int $variantId = null, ?Affiliate $aff = null): string
    {
        $q = array_filter(['variant' => $variantId ?: null], fn ($v) => $v !== null);
        if ($aff) {
            $q += ['utm_source' => 'affiliate', 'aff' => $aff->uuid];
        }

        return tenant_url($tenant, '/'.$tenant->default_locale.'/product/'.$slug).($q ? '?'.http_build_query($q) : '');
    }

    /** A forced rebuild gets a new file name so browsers / CDN never serve the replaced image. */
    private function path(int $tenantId, Model $owner, string $hash, bool $fresh = false): string
    {
        $prefix = match (true) {
            $owner instanceof TenantProductVariant => 'v',
            $owner instanceof Bundle => 'b',
            default => 'p',
        };

        return "tenants/{$tenantId}/".trim((string) config('qr.dir'), '/').'/'.$prefix.$owner->getKey().'-'.substr($hash, 0, 20).($fresh ? '-'.dechex(time()) : '').'.png';
    }

    private function key(string $type, int $id, ?int $affiliateId): string
    {
        return $type.':'.$id.':'.($affiliateId ?? 0);
    }

    private function sellable(TenantProduct $tp): bool
    {
        return $tp->is_active && (bool) $tp->product?->is_active;
    }

    private function variantSellable(TenantProductVariant $tv): bool
    {
        return $tv->is_active && $tv->variant && ! $tv->variant->trashed() && $tv->variant->is_active;
    }

    private function approvedAffiliates(): Collection
    {
        return Affiliate::query()->where('status', AffiliateStatus::APPROVED->value)->get();
    }
}
