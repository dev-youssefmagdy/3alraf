import { z } from 'zod';
import { defineTool, type AnyTool } from './define.js';
import { id, paging, range, search, state } from './schemas.js';

const DATE_NOTE = 'Currency and timezone are in `meta`. Missing or incomplete data is reported in `warnings`; figures that need it are null, never estimated.';

const simple = (name: string, title: string, description: string, path: string, input: z.ZodRawShape = {}): AnyTool =>
  defineTool({ name, title, description, kind: 'read', input, run: (args, ctx) => ctx.laravel.get(path, args as Record<string, string | number | boolean | undefined>) });

const byProduct = (name: string, title: string, description: string, suffix: string, input: z.ZodRawShape = {}): AnyTool =>
  defineTool({
    name,
    title,
    description,
    kind: 'read',
    input: { product_id: id, ...input },
    run: ({ product_id, ...query }, ctx) => ctx.laravel.get(`products/${product_id}${suffix}`, query as Record<string, string | number | boolean | undefined>),
  });

export const readTools: AnyTool[] = [
  simple('get_store_overview', 'Store overview', `Sales, profit, orders, merchandising counts and gift-finder stats for a date range. ${DATE_NOTE}`, 'overview', range),
  simple('get_products', 'Products', 'Paginated product catalog with pricing, margin, stock and tags.', 'products', {
    ...paging,
    category: id.optional().describe('Category id, including its sub-categories.'),
    brand: id.optional().describe('Brand id.'),
    active: z.boolean().optional(),
    in_stock: z.boolean().optional(),
    search: search.optional().describe('Name (ar/en) or SKU.'),
    sort: z.enum(['newest', 'oldest', 'name', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc']).optional(),
  }),
  byProduct('get_product_details', 'Product details', 'Full product: description, variants, pricing, stock, images, SEO, gift tags, URLs.', ''),
  byProduct('get_product_profitability', 'Product profitability', `Units sold, revenue, gross profit and current unit economics for one product. ${DATE_NOTE}`, '/profitability', range),
  byProduct('get_product_images', 'Product images', 'Public gallery images of a product and its variants.', '/images'),
  simple('get_categories', 'Categories', 'Category tree with active product counts.', 'categories', paging),
  simple('get_brands', 'Brands', 'Brands with active product counts.', 'brands', paging),
  simple('get_inventory', 'Inventory', 'On hand, reserved and available stock per product/variant, with stock value at average cost.', 'inventory', paging),
  simple('get_low_stock_products', 'Low stock', 'Items at or below their alert quantity.', 'low-stock', paging),
  simple('get_best_sellers', 'Best sellers', 'Products ranked by units sold in a date range.', 'best-sellers', { ...range, ...paging }),
  simple('get_slow_moving_products', 'Slow movers', 'Active in-stock products with little or no sales in a date range.', 'slow-moving', { ...range, ...paging }),
  simple('get_sales_summary', 'Sales summary', `Sales per day, week or month. ${DATE_NOTE}`, 'sales-summary', { ...range, ...paging, group_by: z.enum(['day', 'week', 'month']).optional() }),
  simple('get_orders_summary', 'Orders summary', 'Aggregate order counts, cancellations, returns and channels. Contains no customer data.', 'orders-summary', range),
  simple('get_campaign_candidates', 'Campaign candidates', 'Products ranked by campaign suitability (velocity, margin, stock cover, recency) with an explanation.', 'campaign-candidates', { ...range, ...paging }),
  simple('get_marketing_performance', 'Marketing performance', `Coupons, flash sales, channels, affiliates and conversion proxies. Ad spend is null here; see get_meta_campaign_insights. ${DATE_NOTE}`, 'marketing-metrics', range),
  simple('get_bundles', 'Bundles', 'Bundles with price, savings, margin and items.', 'bundles', { ...range, ...paging, state: state.optional() }),
  simple('get_sliders', 'Sliders and banners', 'Homepage sliders and other banners.', 'banners', { ...paging, placement: z.enum(['home_hero', 'home_strip', 'category_tile', 'popup']).optional(), state: state.optional() }),
  simple('get_current_offers', 'Flash sales', 'Flash sales with item prices, margins and quantity limits.', 'offers', { ...paging, state: state.optional() }),
  simple('get_coupons', 'Coupons', 'Discount codes with rules, usage and usage in a date range.', 'coupons', { ...range, ...paging, state: state.optional() }),
  simple('get_current_theme', 'Current theme', 'Storefront colour tokens, the active seasonal theme and the tokens a seasonal theme may override.', 'theme'),
  simple('get_static_pages', 'Static pages', 'Static pages with excerpts; include_content adds the plain body.', 'static-pages', { ...paging, include_content: z.boolean().optional() }),
  simple('get_seo_data', 'SEO data', 'Slugs and meta tags per entity, with missing/too-long flags.', 'seo-data', {
    ...paging,
    entity_type: z.enum(['product', 'category', 'brand', 'bundle', 'static_page', 'blog_post', 'site']).optional(),
    id: id.optional(),
    missing_only: z.boolean().optional(),
  }),
  simple('get_existing_blog_posts', 'Blog posts', 'Blog posts. State filter: live/active = published, upcoming = scheduled, inactive = draft.', 'blog-posts', { ...paging, include_content: z.boolean().optional(), state: state.optional() }),
  simple('get_blog_categories', 'Blog categories', 'Blog categories. The store has none today, so this returns an empty list with a warning.', 'blog-categories'),
  simple('get_seasonal_calendar', 'Seasonal calendar', 'Upcoming occasions with dates and days left. Hijri dates are approximate.', 'seasonal-calendar', { days: z.number().int().min(1).max(366).optional().describe('Look-ahead window, default 90.') }),
  simple('get_gift_finder_stats', 'Gift finder stats', 'Gift finder sessions, completion, cart and order rates by occasion.', 'gift-finder/stats', { days: z.number().int().min(1).max(366).optional() }),
];
