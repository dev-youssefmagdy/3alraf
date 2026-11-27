<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiApplyTitleRequest;
use App\Http\Requests\Admin\ProductAiIdentifyRequest;
use App\Http\Requests\Admin\ProductAiPricesRequest;
use App\Http\Requests\Admin\ProductAiTitlesRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use App\Services\ProductAi\MarketPriceService;
use App\Services\ProductAi\ProductIdentifier;
use App\Services\ProductAi\TitleSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Product edit → AI tools (PRODUCT_AI_FREELLMAPI_PLAN.md §3.1): identify a product from a photo, marketplace prices, title ideas and
 * saving a chosen title. Success: {success: true, data}. Gateway errors: {success: false, message, code} with the contract's HTTP status.
 */
class ProductAiController extends Controller
{
    public function __construct(private FreeLlmClient $llm) {}

    public function status(Product $product): JsonResponse
    {
        return $this->ok(null, null, ['configured' => $this->llm->configured(), 'market' => config('product_ai.market'),
            'locales' => config('product_ai.locales'), 'upload_max_kb' => (int) config('product_ai.upload_max_kb')]);
    }

    public function identify(ProductAiIdentifyRequest $request, Product $product, ProductIdentifier $identifier): JsonResponse
    {
        if (! $this->llm->configured()) {
            return $this->fail(new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED));
        }
        $source = (string) $request->validated('source');
        $bytes = match ($source) {
            'upload' => (string) file_get_contents($request->file('image')->getRealPath()),
            'variant' => ProductIdentifier::storedImage($product, $this->variant($product, $request->validated('variant_id'), 'variant_id')),
            default => ProductIdentifier::storedImage($product),
        };
        if ($bytes === null || $bytes === '') {
            throw ValidationException::withMessages(['image' => __('admin.product_ai.no_primary')]);
        }

        try {
            return $this->ok(null, null, $identifier->identify($bytes));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['image' => __('admin.product_ai.image_unreadable')]);
        } catch (LlmUnavailableException $e) {
            return $this->fail($e);
        }
    }

    public function prices(ProductAiPricesRequest $request, Product $product, MarketPriceService $prices): JsonResponse
    {
        if (! $this->llm->configured()) {
            return $this->fail(new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED));
        }
        if ($request->validated('target_type') === 'variant') {
            $this->variant($product, $request->validated('target_id'));
        }

        return $this->ok(null, null, $prices->search((string) $request->validated('query'), (bool) $request->validated('fresh')));
    }

    public function titles(ProductAiTitlesRequest $request, Product $product, TitleSuggestionService $titles): JsonResponse
    {
        if (! $this->llm->configured()) {
            return $this->fail(new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED));
        }
        $variant = $request->validated('target_type') === 'variant' ? $this->variant($product, $request->validated('target_id')) : null;
        $competitors = $request->boolean('use_competitor_titles') ? array_values((array) $request->validated('competitor_titles')) : [];

        try {
            return $this->ok(null, null, $titles->suggest($product, $variant, $competitors));
        } catch (LlmUnavailableException $e) {
            return $this->fail($e);
        }
    }

    /** Saving a chosen title needs no gateway, so it works even while FreeLLMAPI is down. */
    public function applyTitle(ProductAiApplyTitleRequest $request, Product $product, TitleSuggestionService $titles): JsonResponse
    {
        $type = (string) $request->validated('target_type');
        $result = $titles->apply($product, $type, $request->validated('target_id') ? (int) $request->validated('target_id') : null,
            (string) $request->validated('locale'), (string) $request->validated('title'), $request->user('admin'));

        return $this->ok(__('admin.product_ai.saved', ['lang' => strtoupper($result['locale']), 'target' => $result['target']['label']]), null, $result);
    }

    /** @throws ValidationException */
    private function variant(Product $product, mixed $id, string $field = 'target_id'): ProductVariant
    {
        return $product->variants()->whereKey((int) $id)->first()
            ?? throw ValidationException::withMessages([$field => __('admin.product_ai.bad_variant')]);
    }

    private function fail(LlmUnavailableException $e): JsonResponse
    {
        $headers = $e->retryAfter && $e->errorCode === LlmUnavailableException::QUOTA_EXCEEDED ? ['Retry-After' => (string) $e->retryAfter] : [];

        return response()->json(['success' => false, 'message' => $e->userMessage(), 'code' => $e->errorCode], $e->httpStatus(), $headers);
    }
}
