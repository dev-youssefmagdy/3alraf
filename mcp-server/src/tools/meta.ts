import { z } from 'zod';
import { dataField, defineTool, reviewUrl, type AnyTool, type ToolContext, type ToolOutcome } from './define.js';
import { id, metaCursor, metaDatePreset, metaDraftCreate, metaDraftUpdate, metaObjectId, paging, range, search } from './schemas.js';
import type { LaravelSuccess, Query } from '../laravel-client.js';

const draftStatus = z.enum(['draft', 'pending_approval', 'approved', 'rejected', 'publishing', 'published', 'failed', 'archived']);
const cursorPaging = {
  limit: z.number().int().min(1).max(100).optional(),
  after: metaCursor.optional().describe('Cursor from meta.next_cursor.'),
};
const insightsInput = {
  object_id: metaObjectId.optional().describe('Campaign, ad set or ad id. Omit for the whole account.'),
  date_preset: metaDatePreset.optional().describe('Use this, or from + to.'),
  ...range,
};

function withReviewLink(result: LaravelSuccess, ctx: ToolContext, draftId: number): ToolOutcome {
  const review_url = reviewUrl(ctx, `/marketer/meta-ads/${draftId}`, dataField(result, 'review_url'));
  return {
    result,
    extra: { review_url },
    notes: [`A person must review this draft in the marketer panel: ${review_url}\nClaude cannot approve or publish it. Publishing happens in the panel and always creates PAUSED objects. Give the owner this link.`],
  };
}

export const metaTools: AnyTool[] = [
  defineTool({ name: 'get_meta_status', title: 'Meta status', description: 'Whether the Meta integration is configured, the Graph version and safety flags.', kind: 'read', input: {}, run: (_a, ctx) => ctx.laravel.get('meta/status') }),
  defineTool({ name: 'get_meta_ad_accounts', title: 'Meta ad accounts', description: 'Ad accounts accessible to the store integration.', kind: 'read', input: {}, run: (_a, ctx) => ctx.laravel.get('meta/ad-accounts') }),
  defineTool({ name: 'get_meta_account', title: 'Meta ad account', description: 'The configured ad account.', kind: 'read', input: {}, run: (_a, ctx) => ctx.laravel.get('meta/account') }),
  defineTool({ name: 'get_meta_campaigns', title: 'Meta campaigns', description: 'Campaigns in the ad account. Budgets are in minor and major units.', kind: 'read', input: cursorPaging, run: (a, ctx) => ctx.laravel.get('meta/campaigns', a as Query) }),
  defineTool({ name: 'get_meta_ad_sets', title: 'Meta ad sets', description: 'Ad sets, optionally for one campaign.', kind: 'read', input: { campaign_id: metaObjectId.optional(), ...cursorPaging }, run: (a, ctx) => ctx.laravel.get('meta/adsets', a as Query) }),
  defineTool({ name: 'get_meta_ads', title: 'Meta ads', description: 'Ads, optionally for one ad set.', kind: 'read', input: { adset_id: metaObjectId.optional(), ...cursorPaging }, run: (a, ctx) => ctx.laravel.get('meta/ads', a as Query) }),
  defineTool({
    name: 'get_meta_campaign_insights',
    title: 'Meta campaign insights',
    description: 'Spend, reach, clicks, CTR, CPC, CPM, conversions and ROAS at account, campaign or ad-set level. Unavailable metrics are null.',
    kind: 'read',
    input: { ...insightsInput, level: z.enum(['account', 'campaign', 'adset']).optional() },
    run: (a, ctx) => ctx.laravel.get('meta/insights', a as Query),
  }),
  defineTool({
    name: 'get_meta_ad_insights',
    title: 'Meta ad insights',
    description: 'Spend, reach, clicks, CTR, CPC, CPM, conversions and ROAS per ad. Unavailable metrics are null.',
    kind: 'read',
    input: insightsInput,
    run: (a, ctx) => ctx.laravel.get('meta/insights', { ...(a as Query), level: 'ad' }),
  }),
  defineTool({
    name: 'list_campaign_drafts',
    title: 'List campaign drafts',
    description: 'Saved Meta campaign drafts.',
    kind: 'read',
    input: { ...paging, status: draftStatus.optional(), mine: z.boolean().optional(), search: search.optional() },
    run: (a, ctx) => ctx.laravel.get('meta/drafts', a as Query),
  }),
  defineTool({
    name: 'get_campaign_draft',
    title: 'Get campaign draft',
    description: 'One draft: full content, checks, approvals, Meta ids and review link.',
    kind: 'read',
    input: { draft_id: id },
    run: ({ draft_id }, ctx) => ctx.laravel.get(`meta/drafts/${draft_id}`),
  }),
  defineTool({
    name: 'create_campaign_draft',
    title: 'Create campaign draft',
    description: 'Save a Meta campaign draft in the store. Nothing is sent to Meta. Set submit to true to also send it for review.',
    kind: 'write',
    input: { ...metaDraftCreate, submit: z.boolean().optional() },
    run: async (args, ctx) => {
      const result = await ctx.laravel.post('meta/drafts', args);
      const created = dataField(result, 'id');
      return typeof created === 'number' ? withReviewLink(result, ctx, created) : result;
    },
  }),
  defineTool({
    name: 'update_campaign_draft',
    title: 'Update campaign draft',
    description: 'Edit a draft created through this connection while it is draft, pending_approval or rejected. Each field sent replaces the stored one; a content change returns it to draft.',
    kind: 'write',
    input: { draft_id: id, ...metaDraftUpdate },
    run: async ({ draft_id, ...body }, ctx) => withReviewLink(await ctx.laravel.patch(`meta/drafts/${draft_id}`, body), ctx, draft_id),
  }),
  defineTool({
    name: 'submit_campaign_for_approval',
    title: 'Submit campaign draft for review',
    description: 'Send a draft to a person for review and get the panel link. This does not approve or publish it.',
    kind: 'write',
    input: { draft_id: id },
    run: async ({ draft_id }, ctx) => withReviewLink(await ctx.laravel.post(`meta/drafts/${draft_id}/submit`), ctx, draft_id),
  }),
];
