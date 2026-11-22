<?php

namespace App\Services\Casting;

use App\Models\CastingLiveProduct;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/** What the casting / ad / video services need to know about "the product", whether it is a local catalogue product or a snapshot of a live 3alraf.com product. */
final class Subject
{
    public function __construct(
        public readonly ?Product $product,
        public readonly ?CastingLiveProduct $live,
    ) {}

    public static function of(Product|CastingLiveProduct|self $x): self
    {
        return match (true) {
            $x instanceof self => $x,
            $x instanceof Product => new self($x, null),
            default => new self(null, $x),
        };
    }

    public static function resolve(?int $productId, ?int $liveProductId): self
    {
        if ($liveProductId) {
            return new self(null, CastingLiveProduct::findOrFail($liveProductId));
        }

        return new self(Product::findOrFail($productId), null);
    }

    public function productId(): ?int
    {
        return $this->product?->id;
    }

    public function liveProductId(): ?int
    {
        return $this->live?->id;
    }

    public function name(): string
    {
        return $this->product ? (string) $this->product->name : $this->live->displayName();
    }

    public function description(): string
    {
        if ($this->product) {
            return trim(strip_tags((string) $this->product->description));
        }
        $d = (array) $this->live->description;

        return trim((string) ($d[app()->getLocale()] ?? $d['en'] ?? $d['ar'] ?? ''));
    }

    /** @return list<string> */
    public function categoryNames(): array
    {
        if ($this->product) {
            $this->product->loadMissing('categories');

            return $this->product->categories->map(fn ($c) => (string) $c->name)->filter()->values()->all();
        }

        return array_values(array_filter((array) $this->live->categories));
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        if ($this->product) {
            $this->product->loadMissing('tags');

            return $this->product->tags->map(fn ($t) => (string) $t->name)->all();
        }

        return array_values(array_filter((array) $this->live->tags));
    }

    public function brandName(): ?string
    {
        return $this->product?->loadMissing('brand')->brand?->name ? (string) $this->product->brand->name : null;
    }

    public function price(): float
    {
        return $this->product ? (float) ($this->product->sale_price ?: $this->product->price) : (float) $this->live->price;
    }

    public function giftRecipients(): array
    {
        return array_values(array_filter((array) ($this->product ? $this->product->gift_recipients : $this->live->gift_recipients)));
    }

    /** Bytes of the primary product photo (original untouched; callers only read). */
    public function imageBytes(): ?string
    {
        if ($this->product) {
            $img = $this->product->images()->first();

            return $img ? Storage::disk(config('media.disk'))->get($img->path) : null;
        }

        return $this->live->image_path ? CastingStorage::disk()->get($this->live->image_path) : null;
    }

    public function hasImage(): bool
    {
        return $this->product ? $this->product->images()->exists() : (bool) $this->live->image_path;
    }
}
