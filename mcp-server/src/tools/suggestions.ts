import { z } from 'zod';
import { dataField, defineTool, reviewUrl, type AnyTool, type ToolContext, type ToolOutcome } from './define.js';
import * as s from './schemas.js';
import { LaravelError, type LaravelSuccess, type Query } from '../laravel-client.js';

const suggestionTypes = z.enum(['product', 'new_product', 'bundle', 'slider', 'offer', 'coupon', 'seo', 'blog', 'seasonal_theme', 'social_content', 'media_asset', 'category']);
const statuses = z.enum(['pending', 'under_review', 'edited', 'approved', 'rejected', 'applying', 'applied', 'failed', 'expired', 'archived']);

const reviewNote = (url: string): string =>
  `A person must review this in the marketer panel: ${url}\nClaude cannot approve, apply or publish it. Give the owner this link.`;

function withReviewLink(result: LaravelSuccess, ctx: ToolContext, suggestionId: number): ToolOutcome {
  const review_url = reviewUrl(ctx, `/marketer/suggestions/${suggestionId}`, dataField(result, 'review_url'));
  return { result, extra: { review_url }, notes: [reviewNote(review_url)] };
}

interface CreateSpec {
  type: string;
  name: string;
  title: string;
  description: string;
  payload: z.ZodType;
  target?: z.ZodRawShape;
}

const required = (type: string) => ({ target_entity_type: z.literal(type), target_entity_id: s.id });
const optional = (type: string) => ({ target_entity_type: z.literal(type).optional(), target_entity_id: s.id.optional() });

const REVIEW_LINE = 'Saved as a pending suggestion for a person to review; nothing changes in the store.';

const CREATE_SPECS: CreateSpec[] = [
  { type: 'product', name: 'create_product_suggestion', title: 'Suggest product changes', description: `Suggest merchandising changes for a product (wording, tags, gift tags, pins). ${REVIEW_LINE}`, payload: s.productPayload, target: required('product') },
  {
    type: 'new_product',
    name: 'create_new_product_suggestion',
    title: 'Suggest a new product',
    description: `Suggest a NEW product with variants and images (by URL). Use real ids from get_brands, get_categories, get_tags and get_attributes, and check get_products first to avoid duplicates. If no attribute or value fits, propose it in new_attributes / new_values and use its ref in the variants: it is created together with the product when a person approves and applies the suggestion. The product is created as a hidden draft to review and publish. ${REVIEW_LINE}`,
    payload: s.newProductPayload,
  },
  {
    type: 'category',
    name: 'create_category_suggestion',
    title: 'Suggest category changes',
    description: `Suggest a new name, description and/or image for an existing category. Use the id from get_categories (it also shows the current image). The image is given by public URL; the store downloads and checks it, and the reviewer sees current vs proposed. ${REVIEW_LINE}`,
    payload: s.categoryPayload,
    target: required('category'),
  },
  { type: 'bundle', name: 'create_bundle_suggestion', title: 'Suggest a bundle', description: `Suggest a new bundle (no target) or changes to one (target bundle). ${REVIEW_LINE}`, payload: s.bundlePayload, target: optional('bundle') },
  { type: 'slider', name: 'create_slider_suggestion', title: 'Suggest a slider or banner', description: `Suggest a new banner (no target) or changes to one (target banner). Images are uploaded by the owner. ${REVIEW_LINE}`, payload: s.sliderPayload, target: optional('banner') },
  { type: 'offer', name: 'create_offer_suggestion', title: 'Suggest a flash sale', description: `Suggest a new flash sale (no target) or changes to one (target flash_sale). ${REVIEW_LINE}`, payload: s.offerPayload, target: optional('flash_sale') },
  { type: 'coupon', name: 'create_coupon_suggestion', title: 'Suggest a coupon', description: `Suggest a new discount code (no target) or changes to one (target discount_code). Sales below cost are refused. ${REVIEW_LINE}`, payload: s.couponPayload, target: optional('discount_code') },
  {
    type: 'seo',
    name: 'create_seo_suggestion',
    title: 'Suggest SEO changes',
    description: `Suggest meta tags or a slug for one entity. target_entity_id is required except for site. ${REVIEW_LINE}`,
    payload: s.seoPayload,
    target: { target_entity_type: z.enum(['product', 'category', 'brand', 'bundle', 'static_page', 'blog_post', 'custom_page', 'site']), target_entity_id: s.id.optional() },
  },
  { type: 'blog', name: 'create_blog_suggestion', title: 'Suggest a blog post', description: `Suggest a blog post; applying it creates a draft post. ${REVIEW_LINE}`, payload: s.blogPayload },
  { type: 'seasonal_theme', name: 'create_seasonal_theme_suggestion', title: 'Suggest a seasonal theme', description: `Suggest a seasonal colour theme. Check get_current_theme for allowed tokens. ${REVIEW_LINE}`, payload: s.seasonalThemePayload },
  { type: 'social_content', name: 'create_product_social_content_suggestion', title: 'Suggest social content', description: `Suggest a social post for a product. ${REVIEW_LINE}`, payload: s.socialContentPayload, target: required('product') },
  { type: 'media_asset', name: 'create_media_asset_suggestion', title: 'Suggest a media asset', description: `Suggest an image or video brief for a product; no file is generated. ${REVIEW_LINE}`, payload: s.mediaAssetPayload, target: required('product') },
];

const updateName = (spec: CreateSpec): string => spec.name.replace(/^create_/, 'update_');

const createTools: AnyTool[] = CREATE_SPECS.map((spec) =>
  defineTool({
    name: spec.name,
    title: spec.title,
    description: `${spec.description} To change it later, use ${updateName(spec)}.`,
    kind: 'write',
    input: { ...s.suggestionCommon, ...(spec.target ?? {}), payload: spec.payload },
    run: async (args, ctx) => {
      const { source_meta, ...rest } = args as Record<string, unknown> & { source_meta?: Record<string, unknown> };
      const result = await ctx.laravel.post(`suggestions/${spec.type}`, {
        ...rest,
        source: 'mcp',
        source_meta: { ...(source_meta ?? {}), tool: spec.name },
      });
      const created = dataField(result, 'id');
      return typeof created === 'number' ? withReviewLink(result, ctx, created) : result;
    },
  }),
);

/** Common editable fields of a draft (PATCH sends only what is given). */
const editInput = {
  suggestion_id: s.id,
  title: s.suggestionCommon.title.optional(),
  description: s.suggestionCommon.description,
  reasoning: s.suggestionCommon.reasoning,
  priority: s.suggestionCommon.priority,
  expires_at: s.suggestionCommon.expires_at,
  note: z.string().max(500).optional().describe('Revision note shown to the reviewer.'),
};

/** Typed edit of an own pending/edited draft: the stored suggestion must be of this tool's type; `payload`, when sent, replaces the whole payload. */
const updateTools: AnyTool[] = CREATE_SPECS.map((spec) =>
  defineTool({
    name: updateName(spec),
    title: spec.title.replace(/^Suggest/, 'Update suggested'),
    description:
      `Edit a ${spec.type} suggestion made by ${spec.name} (through this connection) while it is pending or edited. ` +
      'Send only the fields to change; `payload`, when sent, replaces the whole payload, so read get_suggestion first and send the full new payload. ' +
      'The target cannot be changed (create a new suggestion instead). A person still reviews it; nothing changes in the store.',
    kind: 'write',
    input: { ...editInput, payload: spec.payload.optional() },
    run: async ({ suggestion_id, ...body }, ctx) => {
      const current = await ctx.laravel.get(`suggestions/${suggestion_id}`);
      const type = dataField(current, 'type');
      if (type !== spec.type) {
        throw new LaravelError('type_mismatch', `Suggestion ${suggestion_id} is of type "${String(type)}", not "${spec.type}". Use the update tool of its type.`, null);
      }
      return withReviewLink(await ctx.laravel.patch(`suggestions/${suggestion_id}`, body), ctx, suggestion_id);
    },
  }),
);

const listInput = {
  ...s.paging,
  type: suggestionTypes.optional(),
  status: statuses.optional(),
  target_entity_type: z.string().max(30).optional(),
  target_entity_id: s.id.optional(),
  mine: z.boolean().optional().describe('Only suggestions created through this connection.'),
  search: s.search.optional(),
  sort: z.enum(['newest', 'oldest', 'priority', 'updated']).optional(),
  ...s.range,
};

const listTool = (name: string, description: string): AnyTool =>
  defineTool({ name, title: 'List suggestions', description, kind: 'read', input: listInput, run: (args, ctx) => ctx.laravel.get('suggestions', args as Query) });

const getTool = (name: string, description: string): AnyTool =>
  defineTool({ name, title: 'Get suggestion', description, kind: 'read', input: { suggestion_id: s.id }, run: ({ suggestion_id }, ctx) => ctx.laravel.get(`suggestions/${suggestion_id}`) });

export const suggestionTools: AnyTool[] = [
  listTool('list_suggestions', 'List saved suggestions with filters.'),
  listTool('get_existing_suggestions', 'Same as list_suggestions. Check it before creating a suggestion, to avoid duplicates.'),
  getTool('get_suggestion', 'One suggestion: payload, current vs proposed, validation, revisions.'),
  getTool('get_suggestion_details', 'Same as get_suggestion.'),
  defineTool({
    name: 'get_suggestion_status',
    title: 'Suggestion status',
    description: 'Status, version, content hash and approval state of a suggestion.',
    kind: 'read',
    input: { suggestion_id: s.id },
    run: ({ suggestion_id }, ctx) => ctx.laravel.get(`suggestions/${suggestion_id}/status`),
  }),
  defineTool({
    name: 'get_suggestion_application_result',
    title: 'Suggestion result',
    description: 'Read-only: what happened after a person acted on the suggestion (executions, before/after, resulting entity).',
    kind: 'read',
    input: { suggestion_id: s.id },
    run: ({ suggestion_id }, ctx) => ctx.laravel.get(`suggestions/${suggestion_id}/result`),
  }),
  ...createTools,
  ...updateTools,
  defineTool({
    name: 'update_suggestion_draft',
    title: 'Update suggestion draft',
    description:
      'Generic edit of a suggestion created through this connection while it is pending or edited. Prefer the typed update_*_suggestion tool of its type ' +
      '(update_product_suggestion, update_slider_suggestion, ...), which checks the payload shape. `payload`, when sent, replaces the whole payload and must match the type schema.',
    kind: 'write',
    input: { ...editInput, payload: z.record(z.string(), z.unknown()).optional() },
    run: async ({ suggestion_id, ...body }, ctx) => withReviewLink(await ctx.laravel.patch(`suggestions/${suggestion_id}`, body), ctx, suggestion_id),
  }),
  defineTool({
    name: 'submit_suggestion_for_review',
    title: 'Submit suggestion for review',
    description: 'Mark a draft suggestion ready for human review and get the panel link. This does not approve or apply it.',
    kind: 'write',
    input: { suggestion_id: s.id },
    run: async ({ suggestion_id }, ctx) => withReviewLink(await ctx.laravel.post(`suggestions/${suggestion_id}/submit`), ctx, suggestion_id),
  }),
];
