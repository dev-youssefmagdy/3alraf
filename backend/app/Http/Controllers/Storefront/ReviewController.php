<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\ReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ReviewRequest;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function validateForm(ReviewRequest $r, string $locale, string $slug): JsonResponse
    {
        $this->service->purchaseOrder(auth('web')->user(), Product::where('slug', $slug)->firstOrFail()) ?? abort(422, __('growth.review_not_purchased'));

        return $this->ok();
    }

    public function store(ReviewRequest $r, string $locale, string $slug): JsonResponse
    {
        $this->service->submit(auth('web')->user(), Product::where('slug', $slug)->firstOrFail(), ReviewData::fromRequest($r));

        return $this->ok(__('growth.review_submitted'), route('storefront.product.show', ['locale' => $locale, 'slug' => $slug]));
    }
}
