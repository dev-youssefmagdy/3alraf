<?php

namespace App\Services;

use App\DTOs\ReviewData;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Validation\ValidationException;

/** Reviews: purchased products only (delivered order), one per customer+product, moderated before they show. */
class ReviewService
{
    public function __construct(private ReviewRepositoryInterface $reviews) {}

    public function find(int|string $id): Review
    {
        return $this->reviews->findOrFail($id);
    }

    /** Delivered order of the customer containing the product, or null. */
    public function purchaseOrder(Customer $c, Product $p): ?Order
    {
        return Order::query()->where('customer_id', $c->id)->where('status', OrderStatus::DELIVERED->value)
            ->whereHas('items', fn ($q) => $q->where('product_id', $p->id))->latest('id')->first();
    }

    public function submit(Customer $c, Product $p, ReviewData $d): Review
    {
        $order = $this->purchaseOrder($c, $p) ?? throw ValidationException::withMessages(['rating' => __('growth.review_not_purchased')]);
        $existing = $this->reviews->query()->where('customer_id', $c->id)->where('product_id', $p->id)->first();
        $data = ['rating' => $d->rating, 'body' => $d->body, 'status' => ReviewStatus::PENDING, 'order_id' => $order->id];

        return $existing ? $this->reviews->update($existing, $data) : $this->reviews->create($data + ['customer_id' => $c->id, 'product_id' => $p->id]);
    }

    public function moderate(Review $r, ReviewStatus $status): Review
    {
        return $this->reviews->update($r, ['status' => $status]);
    }

    public function delete(Review $r): void
    {
        $this->reviews->delete($r);
    }

    /** @return array{count:int,avg:float} approved reviews only */
    public function summary(Product $p): array
    {
        $row = $this->reviews->query()->where('product_id', $p->id)->where('status', ReviewStatus::APPROVED->value)->selectRaw('COUNT(*) c, COALESCE(AVG(rating),0) a')->first();

        return ['count' => (int) $row->c, 'avg' => round((float) $row->a, 1)];
    }
}
