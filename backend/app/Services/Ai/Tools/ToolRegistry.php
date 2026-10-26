<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Contracts\AiTool;

/** The read-only tool set exposed to the chat assistant. There are deliberately no write tools. */
class ToolRegistry
{
    /** @return array<int,AiTool> */
    public function chatTools(): array
    {
        return [new SearchProductsTool, new GetProductTool, new ListBundlesTool, new ListOffersTool, new SearchOrdersTool, new GetOrderTool,
            new SalesSummaryTool, new LowStockTool, new TopSellersTool, new CustomersSummaryTool];
    }
}
