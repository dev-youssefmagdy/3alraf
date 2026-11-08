import { z } from 'zod';
import { defineTool, type AnyTool } from './define.js';
import { id, paging, search } from './schemas.js';
import type { Query } from '../laravel-client.js';

const filters = {
  ...paging,
  kind: z.enum(['image', 'video', 'post']).optional(),
  status: z.enum(['draft', 'pending_review', 'approved', 'rejected', 'archived']).optional(),
  platform: z.enum(['facebook', 'instagram', 'instagram_story', 'reels', 'tiktok', 'whatsapp', 'other']).optional(),
  campaign: z.string().max(120).optional(),
  tag: z.string().max(60).optional(),
  search: search.optional(),
  sort: z.enum(['newest', 'oldest']).optional(),
};

export const mediaTools: AnyTool[] = [
  defineTool({
    name: 'get_product_social_media_assets',
    title: 'Product social media',
    description: 'Private social-media library items of one product: metadata and a preview link that expires in 10 minutes.',
    kind: 'read',
    input: { product_id: id, ...filters },
    run: ({ product_id, ...query }, ctx) => ctx.laravel.get(`products/${product_id}/social-media`, query as Query),
  }),
  defineTool({
    name: 'list_social_media_assets',
    title: 'Social media library',
    description: 'Search the private social-media library. Items are internal and never public.',
    kind: 'read',
    input: { product_id: id.optional(), ...filters },
    run: (args, ctx) => ctx.laravel.get('media', args as Query),
  }),
  defineTool({
    name: 'get_social_media_asset',
    title: 'Social media asset',
    description: 'One library item with its versions and a preview link that expires in 10 minutes.',
    kind: 'read',
    input: { asset_id: id },
    run: ({ asset_id }, ctx) => ctx.laravel.get(`media/${asset_id}`),
  }),
];
