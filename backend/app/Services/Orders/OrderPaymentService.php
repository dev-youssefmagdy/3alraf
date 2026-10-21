<?php

namespace App\Services\Orders;

use App\DTOs\Payments\PaymentResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Events\OrderPaymentProofUploaded;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Wallet transfer proof (upload -> approve/reject) and online gateway settlement. */
class OrderPaymentService
{
    public function __construct(private OrderStatusService $status) {}

    public function uploadProof(Order $order, UploadedFile $file): Order
    {
        $method = $order->paymentMethod;
        if (! $method || $method->type !== PaymentMethodType::WALLET || in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true)
            || in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)) {
            throw ValidationException::withMessages(['proof' => __('orders.proof_not_allowed')]);
        }
        if ($order->payment_proof) {
            Storage::disk('public')->delete($order->payment_proof);
        }
        $path = $file->store('payment-proofs/'.$order->tenant_id, 'public');
        $order->update(['payment_proof' => $path, 'proof_status' => ProofStatus::PENDING, 'payment_status' => PaymentStatus::REVIEW]);
        event(new OrderPaymentProofUploaded($order));

        return $order;
    }

    public function approveProof(Order $order): Order
    {
        $this->ensureReview($order);
        $order->update(['proof_status' => ProofStatus::APPROVED, 'payment_status' => PaymentStatus::PAID, 'paid_amount' => $order->total]);
        $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => __('orders.proof_approved_note'), 'user_id' => auth('tenant')->id()]);
        if ($order->status === OrderStatus::PENDING) {
            $this->status->change($order, OrderStatus::CONFIRMED, __('orders.proof_approved_note'));
        }

        return $order->refresh();
    }

    public function rejectProof(Order $order, ?string $reason = null): Order
    {
        $this->ensureReview($order);
        $order->update(['proof_status' => ProofStatus::REJECTED, 'payment_status' => PaymentStatus::UNPAID]);
        $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => trim(__('orders.proof_rejected_note').' '.$reason), 'user_id' => auth('tenant')->id()]);

        return $order;
    }

    /** Gateway webhook result -> order. Idempotent. */
    public function settleGateway(Order $order, PaymentResult $result): Order
    {
        if ($order->payment_status === PaymentStatus::PAID || $order->status === OrderStatus::CANCELLED) {
            return $order;
        }
        if ($result->status === 'paid') {
            $order->update(['payment_status' => PaymentStatus::PAID, 'paid_amount' => $order->total, 'gateway_reference' => $result->gatewayReference ?: $order->gateway_reference]);
            if ($order->status === OrderStatus::PENDING) {
                $this->status->change($order, OrderStatus::CONFIRMED, __('orders.gateway_paid_note'), null);
            }
        } elseif (in_array($result->status, ['failed', 'cancelled'], true)) {
            $order->update(['payment_status' => PaymentStatus::FAILED]);
        }

        return $order->refresh();
    }

    private function ensureReview(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::REVIEW || ! $order->payment_proof) {
            throw ValidationException::withMessages(['proof' => __('orders.no_proof_pending')]);
        }
    }
}
