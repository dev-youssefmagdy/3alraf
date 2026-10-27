<?php

return [
    'menu' => ['inventory' => 'Inventory decisions', 'returns' => 'Return reasons', 'trends' => 'Trend radar', 'ceo' => 'CEO challenge'],
    'common' => ['product' => 'Product', 'status' => 'Status', 'evidence' => 'Evidence', 'owner' => 'Owner', 'due' => 'Due', 'scan' => 'Scan now', 'none' => 'Nothing yet.', 'save' => 'Save', 'before' => 'Before', 'after' => 'After (est.)', 'order' => 'Order', 'return' => 'Return', 'cancellation' => 'Cancellation', 'week' => 'Week'],
    'inv' => [
        'title' => 'Inventory decision engine', 'hint' => 'Per product: velocity, days of cover, age, lead time and MOQ, ABC class and the best financial move. Recommendations only: a reorder becomes an approval, and approving it creates a DRAFT purchase order. Nothing is ever purchased automatically.',
        'cash_tied' => 'Cash tied in stock', 'slow_value' => 'Slow-moving stock value', 'turnover' => 'Inventory turnover (yearly)', 'cover' => 'Days of cover', 'velocity' => 'Units/day', 'age' => 'Stock age (days)', 'lead' => 'Lead time', 'moq' => 'MOQ', 'abc' => 'Class',
        'margin' => 'Margin %', 'action' => 'Recommended', 'impact' => 'Expected impact', 'cash' => 'Cash', 'qty' => 'Qty', 'unknown' => 'unknown',
        'actions' => ['reorder' => 'Reorder', 'promo' => 'Promotion', 'bundle' => 'Bundle', 'wait' => 'Wait', 'none' => 'None'],
        'reasons' => ['low_cover' => 'Cover is below lead time plus the minimum days of cover.', 'slow_stock' => 'Slow stock: cover above the maximum.', 'expiry_urgent' => 'Near end of shelf life: clear now.', 'season_hold' => 'Its season is coming: hold, do not clear.', 'cash_cap' => 'Reorder exceeds the cash cap.', 'no_history' => 'No sales history.'],
        'missing' => ['cost' => 'cost', 'price' => 'price', 'supply_terms' => 'supplier terms', 'stock_age' => 'stock age'],
        'assume' => 'Promotion impact assumes uplift x:u (config), or :b of stock when there is no sales history. It is an estimate.',
        'reorder' => 'Send for approval', 'sent' => 'Sent to the approval queue.', 'not_recommended' => 'A reorder is not recommended for this product right now.', 'need_supplier' => 'Choose an active supplier first.', 'already_pending' => 'A reorder for this product is already pending.',
        'approval_title' => 'Reorder :qty x :product', 'recheck_failed' => 'Cost or quantity no longer valid; nothing was created.', 'over_cap' => 'Over the reorder cash cap; nothing was created.', 'po_note' => 'Created from an approved Growth inventory recommendation (draft).',
        'terms' => 'Supply terms', 'supplier' => 'Supplier', 'shelf' => 'Shelf life (days)', 'stories' => 'Stock-to-Story tasks',
    ],
    'story' => ['usage' => 'Add a how-to-use video or photo for :product (customers keep asking how to use it while stock sits).', 'photos' => 'Reshoot photos for :product (repeated photo questions, weak sales, stock available).', 'size_color' => 'Add size / colour guidance or a bundle for :product (repeated questions, weak sales).', 'scanned' => ':n task(s) created.', 'scan' => 'Find Stock-to-Story tasks'],
    'ret' => [
        'title' => 'Return reason intelligence', 'hint' => 'Tag every return and cancellation with a structured reason. Recurring (reason, product) pairs create a fix task and the problem count is compared before and after.',
        'code' => 'Reason code', 'bad_code' => 'Unknown reason code.', 'not_cancelled' => 'Order is not cancelled.', 'generated' => ':n fix task(s) created.', 'generate' => 'Create fix tasks', 'untagged' => 'Needs a reason code', 'unspecified' => 'Unspecified', 'returns' => 'Returns', 'cancellations' => 'Cancellations', 'value' => 'Value',
        'codes' => ['color_differs' => 'Colour differs from expected', 'size_unclear' => 'Size unclear', 'damaged_packaging' => 'Damaged packaging', 'delivery_delay' => 'Delivery delay', 'description_inaccurate' => 'Description inaccurate', 'quality' => 'Quality problem', 'wrong_item' => 'Wrong item sent', 'changed_mind' => 'Changed mind', 'other' => 'Other'],
        'fix' => [
            'color_differs' => 'Re-shoot :product in daylight and add a colour note and swatch to the listing.', 'size_unclear' => 'Add a size chart and measurements to :product.', 'damaged_packaging' => 'Review packaging for :product and add protection; check with the carrier.',
            'delivery_delay' => 'Review carrier performance and the promised delivery time for :product.', 'description_inaccurate' => 'Correct the description of :product so it matches the real item.', 'quality' => 'Inspect the :product stock and raise a quality complaint with the supplier.', 'wrong_item' => 'Audit picking and labelling for :product.',
        ],
    ],
    'trend' => [
        'title' => 'Trend radar', 'hint' => 'Weekly scan of your own sales movement and the cached market snapshot, scored by configured weights. Market data comes from AI web search and is indicative. Trend-to-Test only creates a draft experiment, never a purchase.',
        'score' => 'Opportunity score', 'components' => ['demand' => 'Demand', 'margin' => 'Margin', 'supplier' => 'Supplier', 'audience' => 'Audience', 'risk' => 'Risk (higher = safer)', 'content' => 'Content'], 'uncertain' => 'Uncertain', 'confidence' => 'Confidence', 'source' => 'Source', 'sources' => ['internal' => 'Own sales', 'market' => 'Market'],
        'test' => 'Trend-to-Test', 'dismiss' => 'Dismiss', 'scanned' => ':n new candidate(s).', 'test_created' => 'Draft experiment created.', 'need_experiments' => 'Turn on the Experiments feature first.',
        'exp_name' => 'Trend test: :name', 'exp_hyp' => 'There is real demand for :name in our audience.', 'exp_obj' => 'Validate demand with content or a small-batch test before any purchase.', 'exp_metric' => 'Interest / clicks / signups per day',
    ],
    'ceo' => [
        'title' => 'Weekly CEO challenge', 'hint' => 'One business question per week, chosen by rules from your own numbers, with a hypothesis, a small experiment, a metric, a duration and a review date.',
        'generate' => 'Generate this week', 'hypothesis' => 'Hypothesis', 'experiment' => 'Small experiment', 'metric' => 'Success metric', 'duration' => 'Duration (days)', 'review' => 'Review on', 'result' => 'Result note', 'to_exp' => 'Create draft experiment',
        'statuses' => ['open' => 'Open', 'reviewed' => 'Reviewed', 'skipped' => 'Skipped'],
        'rules' => [
            'shipping_checkout' => ['question' => 'Is shipping cost preventing customers from completing checkout?', 'hypothesis' => 'Customers abandon carts when shipping is shown late or looks high.', 'experiment' => 'Show the shipping cost earlier, or test a free-shipping threshold slightly above the average order value, for a limited period.', 'metric' => 'Checkout completion rate and abandoned carts'],
            'low_conversion' => ['question' => 'Why do visitors not buy?', 'hypothesis' => 'Trust or product information gaps are lowering conversion.', 'experiment' => 'Improve the top-visited product page (photos, reviews, delivery promise) and compare conversion.', 'metric' => 'Conversion rate of that product'],
            'high_returns' => ['question' => 'What is driving returns?', 'hypothesis' => 'Listings promise something the product does not deliver.', 'experiment' => 'Fix the top return reason on the worst product and track the return rate.', 'metric' => 'Return rate'],
            'high_cancel' => ['question' => 'Why do customers cancel orders?', 'hypothesis' => 'Delivery time or confirmation friction causes cancellations.', 'experiment' => 'Confirm orders by message within an hour for two weeks.', 'metric' => 'Cancel rate'],
            'slow_inventory' => ['question' => 'How can we free cash tied in slow stock?', 'hypothesis' => 'A bundle or a margin-safe promotion will move the slowest product.', 'experiment' => 'Bundle or discount the slowest product within the margin floor for two weeks.', 'metric' => 'Units sold and cash released'],
            'low_repeat' => ['question' => 'How can we bring first-time buyers back?', 'hypothesis' => 'A follow-up message with a relevant offer lifts repeat orders.', 'experiment' => 'Send a follow-up with a small offer to customers 14 days after delivery.', 'metric' => 'Repeat purchase rate'],
            'content_gap' => ['question' => 'Which product deserves better content?', 'hypothesis' => 'Better content on a good product increases its sales.', 'experiment' => 'Produce one new video or photo set for a stocked, healthy-margin product.', 'metric' => 'Units sold of that product'],
        ],
    ],
];
