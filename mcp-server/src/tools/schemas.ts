import { z } from 'zod';

export const id = z.number().int().positive();

const ymd = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'must be YYYY-MM-DD');

export const range = {
  from: ymd.optional().describe('Start date YYYY-MM-DD (shop timezone). Default: 30 days before `to`.'),
  to: ymd.optional().describe('End date YYYY-MM-DD, at most 366 days after `from`. Default: today.'),
};

export const paging = {
  page: z.number().int().min(1).max(100000).optional().describe('Page number, default 1.'),
  per_page: z.number().int().min(1).max(100).optional().describe('Items per page, 1..100, default 25.'),
};

export const state = z.enum(['all', 'active', 'live', 'upcoming', 'ended', 'inactive']);
export const search = z.string().min(1).max(100);

/** Translatable text: { "ar": "...", "en": "..." }. */
export const localized = (max: number) => z.record(z.string().regex(/^[a-z]{2}$/, 'locale code such as ar or en'), z.string().max(max));

export const money = z.union([z.number().nonnegative(), z.string().regex(/^\d+(\.\d{1,2})?$/)]);
export const dateTime = z.string().min(4).max(40).describe('Date-time, e.g. 2027-03-25 00:00 (shop timezone).');
const internalUrl = z.string().min(1).max(500).describe('Site-relative path (/ar/...) or absolute URL on the store host.');
const canvaUrl = z.string().max(500).describe('https://www.canva.com/... link only.');
const advisory = (max: number) => z.string().max(max);

const platform = z.enum(['facebook', 'instagram', 'instagram_story', 'reels', 'tiktok', 'whatsapp', 'other']);

const recipients = z.enum(['mother', 'partner', 'friend', 'sister', 'colleague', 'child', 'self', 'other']);
const occasions = z.enum(['birthday', 'mothers_day', 'valentine', 'wedding', 'eid_fitr', 'eid_adha', 'ramadan', 'graduation', 'thanks', 'self']);

export const productPayload = z
  .strictObject({
    name: localized(200).optional().describe('Storefront wording overlay; the catalog name is kept.'),
    description: localized(20000).optional().describe('HTML allowlist, sanitised by the store.'),
    tags_add: z.array(id).optional().describe('Existing tag ids. Do not combine with tags_remove.'),
    tags_remove: z.array(id).optional(),
    gift: z.strictObject({ suitable: z.boolean(), recipients: z.array(recipients).optional(), occasions: z.array(occasions).optional() }).optional(),
    pin: z.strictObject({ list: z.enum(['recommended', 'most_sold', 'most_reviewed']), position: z.number().int().min(1).max(100).nullable().optional() }).optional(),
    unpin: z.array(z.enum(['recommended', 'most_sold', 'most_reviewed'])).optional(),
  })
  .describe('At least one field is required.');

export const bundlePayload = z.strictObject({
  name: localized(200).optional().describe('Required when creating a new bundle.'),
  description: localized(5000).optional(),
  price: money.optional().describe('> 0. Required when creating. A negative margin is refused.'),
  items: z
    .array(z.strictObject({ product_id: id, variant_id: z.number().int().min(0).optional().describe('0 = simple product.'), quantity: z.number().int().min(1).max(999) }))
    .min(1)
    .max(50)
    .optional()
    .describe('Required when creating.'),
  starts_at: dateTime.optional(),
  ends_at: dateTime.optional(),
  sort_order: z.number().int().optional(),
});

export const sliderPayload = z.strictObject({
  placement: z.enum(['home_hero', 'home_strip', 'category_tile', 'popup']).optional().describe('Required when creating.'),
  title: localized(200).optional(),
  link_url: internalUrl.optional(),
  starts_at: dateTime.optional(),
  ends_at: dateTime.optional(),
  sort_order: z.number().int().optional(),
  design_notes: advisory(2000).optional().describe('Images are uploaded by the owner; suggestions never carry files.'),
});

export const offerPayload = z.strictObject({
  name: localized(200).optional().describe('Required when creating.'),
  starts_at: dateTime.optional().describe('Required when creating.'),
  ends_at: dateTime.optional().describe('Required when creating; must be in the future.'),
  items: z
    .array(
      z.strictObject({
        product_id: id,
        variant_id: z.number().int().min(0).optional(),
        discount_type: z.enum(['percent', 'price']),
        value: money.describe('Percent (≤ 100) or the flash price. A flash price below cost is refused.'),
        qty_limit: z.number().int().min(1).nullable().optional(),
      }),
    )
    .min(1)
    .optional()
    .describe('Required when creating.'),
});

export const couponPayload = z.strictObject({
  code: z.string().regex(/^[A-Za-z0-9_-]{3,50}$/).optional(),
  type: z.enum(['percent', 'fixed']).optional(),
  value: money.optional().describe('Percent ≤ 100.'),
  min_order: money.optional(),
  max_discount: money.optional(),
  usage_limit: z.number().int().min(1).optional(),
  per_customer_limit: z.number().int().min(1).optional(),
  starts_at: dateTime.optional(),
  ends_at: dateTime.optional(),
  scope: z.enum(['all', 'products', 'categories']).optional(),
  product_ids: z.array(id).optional(),
  category_ids: z.array(id).optional(),
  free_shipping: z.boolean().optional(),
});

export const seoPayload = z.strictObject({
  meta_title: localized(120).optional(),
  meta_description: localized(320).optional(),
  slug: z.string().max(150).regex(/^[a-z0-9]+(-[a-z0-9]+)*$/).optional().describe('Not for target `site`. A change creates a 301 from the old URL.'),
  notes: z
    .strictObject({
      h1: localized(200).optional(),
      keywords: z.array(z.string().max(80)).optional(),
      internal_links: z.array(internalUrl).optional(),
      content_improvements: advisory(3000).optional(),
      image_alt: advisory(3000).optional(),
      structured_data: advisory(3000).optional(),
      risks: advisory(3000).optional(),
      expected_benefit: advisory(3000).optional(),
    })
    .optional()
    .describe('Advisory only; shown to the reviewer.'),
});

export const blogPayload = z.strictObject({
  title: localized(200),
  slug: z.string().min(1).max(150).describe('Unique among posts.'),
  excerpt: localized(500).optional(),
  content: localized(100000).optional().describe('HTML allowlist; links must be internal.'),
  meta_title: localized(120).optional(),
  meta_description: localized(200).optional(),
  language: z.enum(['ar', 'en', 'both']).optional(),
  category: advisory(1000).optional(),
  topic: advisory(1000).optional(),
  search_intent: advisory(1000).optional(),
  target_audience: advisory(1000).optional(),
  featured_image_concept: advisory(1000).optional(),
  cta: advisory(1000).optional(),
  occasion: advisory(1000).optional(),
  keywords: z.array(z.string().max(80)).max(30).optional(),
  outline: z.array(z.string().max(300)).max(40).optional(),
  internal_links: z.array(z.strictObject({ url: internalUrl, anchor: z.string().max(150) })).optional(),
  suggested_publish_at: dateTime.optional(),
});

export const seasonalThemePayload = z.strictObject({
  key: z.string().max(60).regex(/^[a-z0-9]+([_-][a-z0-9]+)*$/).describe('Theme family, e.g. ramadan.'),
  name: localized(120),
  tokens: z
    .record(z.string().regex(/^color-[a-z0-9-]+$/), z.string().regex(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/))
    .describe('Allowed token names: get_current_theme → overridable_tokens.'),
  design_direction: advisory(2000).optional(),
  decorations: advisory(2000).optional(),
  typography: advisory(2000).optional(),
  header_footer: advisory(2000).optional(),
  product_cards: advisory(2000).optional(),
  slider: advisory(2000).optional(),
});

export const socialContentPayload = z.strictObject({
  platform,
  format: z.enum(['post', 'story', 'reel', 'carousel', 'video']),
  caption: localized(2200),
  hashtags: z.array(z.string().max(60)).max(30).optional(),
  cta: z.string().max(200).optional(),
  media_brief: z.string().max(2000).optional(),
  campaign: z.string().max(120).optional(),
  suggested_publish_at: dateTime.optional(),
  canva_url: canvaUrl.optional(),
});

export const mediaAssetPayload = z.strictObject({
  asset_kind: z.enum(['image', 'video']),
  brief: z.string().min(1).max(3000),
  title: localized(200).optional(),
  platform: platform.optional(),
  aspect_ratio: z.enum(['1:1', '4:5', '9:16', '16:9']).optional(),
  duration_seconds: z.number().int().min(1).max(600).optional(),
  purpose: z.string().max(500).optional(),
  tags: z.array(z.string().max(60)).max(20).optional(),
  canva_url: canvaUrl.optional(),
});

/** Fields shared by every create_*_suggestion tool. `source` and `source_meta.tool` are set by the server. */
export const suggestionCommon = {
  title: z.string().min(3).max(200).describe('Short title shown to the reviewer.'),
  description: z.string().max(2000).optional(),
  reasoning: z.string().max(5000).optional().describe('The analysis shown to the reviewer.'),
  priority: z.enum(['low', 'normal', 'high', 'urgent']).optional(),
  expires_at: z.string().max(40).optional().describe('Future date-time under a year away. Default: 60 days.'),
  source_meta: z
    .strictObject({ model: z.string().max(80).optional(), run_id: z.string().max(80).optional(), conversation_id: z.string().max(80).optional() })
    .optional(),
  source_data: z.record(z.string(), z.unknown()).optional().describe('Ids, metrics and dates this suggestion relies on. ≤ 20 KB, no customer data.'),
  force_reason: z.string().min(10).max(500).optional().describe('Only to re-create a suggestion rejected in the last 30 days; say what changed.'),
  draft: z.boolean().optional().describe('true = keep as a draft until submit_suggestion_for_review is called.'),
};

const objective = z.enum(['OUTCOME_AWARENESS', 'OUTCOME_TRAFFIC', 'OUTCOME_ENGAGEMENT', 'OUTCOME_LEADS', 'OUTCOME_SALES']);
const cta = z.enum(['SHOP_NOW', 'LEARN_MORE', 'ORDER_NOW', 'BUY_NOW', 'GET_OFFER', 'CONTACT_US', 'SEND_WHATSAPP_MESSAGE', 'SIGN_UP']);
const optimizationGoal = z.enum(['REACH', 'IMPRESSIONS', 'LINK_CLICKS', 'LANDING_PAGE_VIEWS', 'POST_ENGAGEMENT', 'OFFSITE_CONVERSIONS']);

/** Optional draft fields (identical for create and update). */
const metaDraftOptional = {
  end_at: z.string().max(40).nullable().optional().describe('Required for a lifetime budget; after start_at.'),
  currency: z.string().regex(/^[A-Za-z]{3}$/).nullable().optional(),
  audience: z
    .strictObject({
      description: z.string().max(1000).nullable().optional(),
      age_min: z.number().int().min(13).max(65).nullable().optional(),
      age_max: z.number().int().min(13).max(65).nullable().optional(),
      genders: z.enum(['all', 'male', 'female']).nullable().optional(),
      interests: z.array(z.string().max(100)).max(25).nullable().optional(),
    })
    .nullable()
    .optional(),
  locations: z
    .strictObject({
      countries: z.array(z.string().regex(/^[A-Za-z]{2}$/)).max(25).nullable().optional(),
      cities: z.array(z.string().max(100)).max(50).nullable().optional(),
    })
    .nullable()
    .optional(),
  placements: z
    .strictObject({
      automatic: z.boolean().nullable().optional(),
      publisher_platforms: z.array(z.enum(['facebook', 'instagram', 'messenger', 'audience_network'])).nullable().optional(),
      facebook_positions: z.array(z.enum(['feed', 'story', 'facebook_reels', 'marketplace', 'video_feeds', 'search'])).nullable().optional(),
      instagram_positions: z.array(z.enum(['stream', 'story', 'reels'])).nullable().optional(),
    })
    .nullable()
    .optional(),
  creative: z
    .strictObject({
      asset_ids: z.array(id).max(10).nullable().optional().describe('Library asset ids, see list_social_media_assets.'),
      canva_url: canvaUrl.nullable().optional(),
      notes: z.string().max(2000).nullable().optional(),
    })
    .nullable()
    .optional(),
  landing_url: internalUrl.nullable().optional(),
  optimization_goal: optimizationGoal.nullable().optional(),
  expected_kpis: z.record(z.string(), z.union([z.string().max(200), z.number(), z.boolean(), z.null()])).nullable().optional(),
  risks: z.string().max(3000).nullable().optional(),
  assumptions: z.string().max(3000).nullable().optional(),
};

const metaCopy = z.strictObject({
  primary_text: z.string().max(2000),
  headline: z.string().max(255).nullable().optional(),
  description: z.string().max(255).nullable().optional(),
});

export const metaDraftCreate = {
  name: z.string().min(3).max(200),
  objective,
  product_ids: z.array(id).min(1).max(20),
  budget_type: z.enum(['daily', 'lifetime']),
  budget_amount: z.number().min(1).max(10000000),
  start_at: z.string().min(4).max(40),
  copy: metaCopy,
  cta,
  ...metaDraftOptional,
};

export const metaDraftUpdate = {
  name: metaDraftCreate.name.optional(),
  objective: objective.optional(),
  product_ids: metaDraftCreate.product_ids.optional(),
  budget_type: metaDraftCreate.budget_type.optional(),
  budget_amount: metaDraftCreate.budget_amount.optional(),
  start_at: metaDraftCreate.start_at.optional(),
  copy: metaCopy.partial().optional(),
  cta: cta.optional(),
  ...metaDraftOptional,
};

export const metaDatePreset = z.enum(['today', 'yesterday', 'last_7d', 'last_14d', 'last_30d', 'last_90d', 'this_month', 'last_month', 'maximum']);
export const metaObjectId = z.string().regex(/^\d{1,30}$/);
export const metaCursor = z.string().max(200).regex(/^[A-Za-z0-9_\-=]+$/);
