<?php

namespace App\Http\Controllers\Api\Marketing;

use App\DTOs\Marketing\SuggestionInput;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Http\Controllers\Controller;
use App\Models\MarketingApiToken;
use App\Models\Product;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\Suggestions\SuggestionImages;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * POST products/{id}/image-suggestion (scope suggestions:write:product_update): upload product / variant images in ONE call and get a
 * `product_update` suggestion back. Nothing changes in the store: a person reviews the before / after images in the Suggestions Center and
 * applies (or rolls back) them there. multipart:
 *   main            file   -> added and made the main image
 *   gallery[]       files  -> added after it
 *   variants[{id}]  file   -> replaces the image of that variant (one per variant)
 *   replace_gallery 0|1    -> also remove the product's current gallery images (only with main / gallery)
 *   title?, reasoning?, draft? (0|1)
 * Files are staged exactly like images proposed by URL (SuggestionImages: real image check, private disk, sha256 bound to the approval).
 */
class ImageSuggestionController extends Controller
{
    public function __construct(private SuggestionEngine $engine, private SuggestionImages $images) {}

    public function store(Request $r, int $id): JsonResponse
    {
        $kb = (int) ceil((int) config('images.max_source_bytes') / 1024);
        $file = ['file', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.$kb];
        $v = validator($r->all(), [
            'main' => ['nullable', ...$file], 'gallery' => ['nullable', 'array', 'max:9'], 'gallery.*' => $file,
            'variants' => ['nullable', 'array', 'max:100'], 'variants.*' => $file,
            'replace_gallery' => ['nullable', 'boolean'], 'draft' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'min:3', 'max:200'], 'reasoning' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($v->fails()) {
            return R::error(422, 'validation_failed', (string) $v->errors()->first(), $v->errors()->toArray());
        }
        $product = Product::query()->find($id);
        if (! $product) {
            return R::error(404, 'not_found');
        }
        $variants = (array) $r->file('variants', []);
        foreach (array_keys($variants) as $key) {
            if (! ctype_digit((string) $key)) {
                return R::error(422, 'validation_failed', 'variants[] keys must be variant ids.', ['variants' => ['variants[] keys must be variant ids.']]);
            }
        }
        $gallery = array_values(array_filter([$r->file('main'), ...(array) $r->file('gallery', [])]));
        if (! $gallery && ! $variants) {
            return R::error(422, 'validation_failed', 'Send at least one image (main, gallery[] or variants[{id}]).', ['main' => ['required']]);
        }

        $stage = function (UploadedFile $f, string $field) {
            $img = $this->images->stageFile($f->getRealPath());
            if (isset($img['error'])) {
                throw new \InvalidArgumentException($field.': '.__('marketing.suggestions.image_errors.'.$img['error']));
            }

            return ['url' => $img['url']];
        };
        try {
            $payload = [];
            foreach ($gallery as $i => $f) {
                $payload['images_add'][] = $stage($f, $i === 0 && $r->hasFile('main') ? 'main' : "gallery.$i");
            }
            $r->hasFile('main') && $payload['main_image_index'] = 0;
            if ($gallery && $r->boolean('replace_gallery') && ($old = $product->images()->pluck('id')->map(fn ($x) => (int) $x)->all())) {
                $payload['images_remove'] = $old;
            }
            foreach ($variants as $variantId => $f) {
                $payload['variant_images'][] = ['variant_id' => (int) $variantId] + $stage($f, "variants.$variantId");
            }
        } catch (\InvalidArgumentException $e) {
            return R::error(422, 'image_invalid', $e->getMessage());
        }

        $title = $r->input('title') ?: 'صور جديدة: '.mb_substr((string) $product->getTranslation('name', 'ar', false) ?: (string) $product->sku, 0, 150);
        $input = SuggestionInput::fromArray(SuggestionTypeKey::PRODUCT_UPDATE->value, ['title' => $title, 'reasoning' => $r->input('reasoning'),
            'target_entity_type' => 'product', 'target_entity_id' => $product->id, 'payload' => $payload, 'draft' => $r->boolean('draft'),
            'source_meta' => ['run_id' => 'image-suggestion']], 'mcp');
        try {
            /** @var MarketingApiToken $token */
            $token = $r->attributes->get('marketing_token');
            [$s, $warnings] = $this->engine->create($input, SuggestionActor::token($token));
        } catch (SuggestionException $e) {
            return R::error($e->status, $e->errorCode, $e->getMessage(), $e->errors ?: null, $e->data ?: null);
        }

        return R::ok(['id' => $s->id, 'status' => $s->status->value, 'product_id' => $product->id,
            'images_added' => count($payload['images_add'] ?? []), 'images_removed' => count($payload['images_remove'] ?? []), 'variant_images' => count($payload['variant_images'] ?? []),
            'review_url' => rtrim((string) config('app.url'), '/').'/marketer/suggestions/'.$s->id],
            array_map(fn ($w) => R::warning('suggestion', $w), $warnings), [], 201);
    }
}
