import { z } from 'zod';
import { defineTool, type AnyTool } from './define.js';

/**
 * Image-search index (IMAGE_SEARCH_PLAN.md §3.4, MCP.md "Image embeddings"). The fill tool is the only write that is not a suggestion:
 * it touches only the store's internal image-search index and changes no product, bundle, price or any other store content.
 */
export const embeddingTools: AnyTool[] = [
  defineTool({
    name: 'get_image_embedding_status',
    title: 'Image search index status',
    description:
      'Coverage of the admin "search by image" index: how many catalog images (products, variants, bundles) have an embedding for the current model, ' +
      'how many are pending, failed or missing, and whether the embedder service is reachable.',
    kind: 'read',
    input: {},
    run: (_a, ctx) => ctx.laravel.get('image-embeddings/status'),
  }),
  defineTool({
    name: 'fill_missing_image_embeddings',
    title: 'Fill missing image embeddings',
    description:
      'Queue embeddings ONLY for catalog images that are missing one (new images, or images whose file was replaced). ' +
      'It never recomputes or overwrites existing embeddings, and it changes no store content: it only adds to the internal image-search index. ' +
      'Safe to repeat: a second call while jobs are still pending queues 0. Returns how many were queued and the index status.',
    kind: 'write',
    idempotent: true,
    input: {
      limit: z.number().int().min(1).max(5000).optional().describe('Queue at most this many (default: all missing).'),
    },
    run: (args, ctx) => ctx.laravel.post('image-embeddings/fill-missing', args),
  }),
];
