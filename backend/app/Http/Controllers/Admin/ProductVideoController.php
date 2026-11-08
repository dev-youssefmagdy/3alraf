<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\ProductMediaOrderData;
use App\DTOs\ProductVideoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductMediaOrderRequest;
use App\Http\Requests\Admin\ProductVideoRequest;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Services\ProductVideoService;
use App\Services\Storefront\ProductGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Product edit page: public gallery videos and the shared image + video order. Each answer carries the re-rendered card, so unsaved edits in the product form are kept. */
class ProductVideoController extends Controller implements HasMiddleware
{
    public function __construct(private ProductVideoService $service, private ProductGallery $gallery) {}

    public static function middleware(): array
    {
        return [new Middleware('can:products.edit')];
    }

    public function store(ProductVideoRequest $request, Product $product): JsonResponse
    {
        $this->service->upload($product, ProductVideoData::fromRequest($request));

        return $this->card($product, __('admin.saved'));
    }

    public function destroy(Product $product, ProductVideo $video): JsonResponse
    {
        $this->service->delete($video);

        return $this->card($product, __('admin.deleted'));
    }

    public function order(ProductMediaOrderRequest $request, Product $product): JsonResponse
    {
        $this->service->reorder($product, ProductMediaOrderData::fromRequest($request));

        return $this->card($product, __('admin.saved'));
    }

    private function card(Product $product, string $message): JsonResponse
    {
        $product->load(['images', 'videos']);

        return $this->ok($message, null, ['html' => view('admin.products._media', ['product' => $product, 'media' => $this->gallery->items($product)])->render()]);
    }
}
