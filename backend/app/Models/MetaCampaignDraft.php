<?php

namespace App\Models;

use App\Enums\Marketing\MetaDraftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Meta campaign draft (P6, MCP.md §7). `content_hash` = hash of every field sent to Meta (MetaCampaignDraftService::hash); an approval binds to
 * (version, content_hash). Meta object ids are filled by MetaPublisher (all created PAUSED). Written only by the Meta services.
 */
class MetaCampaignDraft extends Model
{
    protected $fillable = ['name', 'objective', 'status', 'product_ids', 'product_urls', 'audience', 'locations', 'placements', 'budget_type', 'budget_amount',
        'currency', 'start_at', 'end_at', 'creative', 'copy', 'cta', 'landing_url', 'optimization_goal', 'expected_kpis', 'risks', 'assumptions', 'version',
        'content_hash', 'created_by_admin_id', 'created_by_token_id', 'submitted_at', 'approved_by', 'approved_at', 'ad_account_id', 'meta_campaign_id',
        'meta_adset_id', 'meta_creative_id', 'meta_ad_id', 'meta_image_hash', 'meta_statuses', 'published_by', 'published_at', 'last_error'];

    protected function casts(): array
    {
        return ['status' => MetaDraftStatus::class, 'product_ids' => 'array', 'product_urls' => 'array', 'audience' => 'array', 'locations' => 'array',
            'placements' => 'array', 'creative' => 'array', 'copy' => 'array', 'expected_kpis' => 'array', 'meta_statuses' => 'array', 'budget_amount' => 'decimal:2',
            'start_at' => 'datetime', 'end_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime', 'version' => 'int'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(MetaCampaignApproval::class, 'draft_id')->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MetaAuditLog::class, 'draft_id')->latest('id');
    }

    public function creatorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(MarketingApiToken::class, 'created_by_token_id');
    }

    /** The approval that currently authorises publishing: approved, not invalidated, not expired, same version + hash. */
    public function validApproval(): ?MetaCampaignApproval
    {
        return MetaCampaignApproval::query()->where('draft_id', $this->id)->where('approval_status', 'approved')->whereNull('invalidated_at')
            ->where('draft_version', $this->version)->where('content_hash', $this->content_hash)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first();
    }

    /** Something already exists in Meta for this draft (content is frozen from then on). */
    public function hasMetaObjects(): bool
    {
        return (bool) ($this->meta_campaign_id || $this->meta_adset_id || $this->meta_creative_id || $this->meta_ad_id);
    }
}
