<?php

namespace App\Http\Controllers\Marketer;

use App\DataTables\MarketingApiTokenDataTable;
use App\DTOs\Marketing\MarketingTokenData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MarketingTokenRequest;
use App\Services\Marketing\MarketingTokenService;
use Illuminate\Http\JsonResponse;

/** Marketing internal API service tokens (perm marketing.tokens.manage on the routes). The plain token is returned once, by store(). */
class MarketingTokenController extends Controller
{
    public function __construct(private MarketingTokenService $tokens) {}

    public function index(MarketingApiTokenDataTable $table)
    {
        return view('marketer.tokens.index', ['table' => $table, 'scopes' => (array) config('marketing.scopes'), 'base' => url('/api/internal/marketing'),
            'expiry' => (array) config('marketing.token_expiry_days'), 'defaultExpiry' => config('marketing.token_default_expiry_days')]);
    }

    public function datatable(MarketingApiTokenDataTable $table)
    {
        return $table->ajax();
    }

    public function validateForm(MarketingTokenRequest $r): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function store(MarketingTokenRequest $r): JsonResponse
    {
        [$token, $plain] = $this->tokens->create(MarketingTokenData::fromRequest($r));

        return response()->json(['success' => true, 'message' => __('marketing.tokens.created'), 'data' => ['id' => $token->id, 'token' => $plain,
            'expires_at' => $token->expires_at?->toDateTimeString()]], 201);
    }

    public function revoke(int $id): JsonResponse
    {
        $this->tokens->revoke($this->tokens->findOrFail($id));

        return $this->ok(__('marketing.tokens.revoked_ok'));
    }
}
