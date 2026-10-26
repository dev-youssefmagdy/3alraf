<?php

namespace App\Services\Orders;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Events\OrderPaymentProofUploaded;
use App\Events\OrderPaymentUpdated;
use App\Exceptions\GatewayException;
use App\Exceptions\GatewayNotImplementedException;
use App\Exceptions\PaymentMismatchException;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGatewayFactory;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Wallet transfer proof (private disk; upload -> approve/reject), online payment start and idempotent gateway settlement. */
class OrderPaymentService
{
    public const PROOF_DISK = 'private';

    /** Payment links per order (each one burns a merchant reference "-R{n}" at the provider); beyond this the customer gets a 422. */
    public const MAX_PAYMENT_ATTEMPTS = 20;

    public function __construct(private OrderStatusService $status, private PaymentGatewayFactory $gateways, private PaymentProofFiles $files) {}

    // ---------------------------------------------------------------- wallet proof

    /** A wallet-paid order that is not settled / closed yet accepts (or replaces) a transfer proof. */
    public function canUploadProof(Order $order): bool
    {
        $method = $order->payingMethod();

        return $method?->type === PaymentMethodType::WALLET && ! in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true)
            && ! in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_PAID, PaymentStatus::REFUNDED], true);
    }

    /** Offline = WALLET type. COD alone never needs proof; COD needs it only when its (actually charged) prepaid-shipping leg goes through a WALLET method. */
    public function offlineAtCheckout(?PaymentMethod $method, ?PaymentMethod $prepay, string|float|int $prepaidShipping = 0): bool
    {
        if ($method?->type === PaymentMethodType::WALLET) {
            return true;
        }

        return $method?->type === PaymentMethodType::COD && $prepay?->type === PaymentMethodType::WALLET && bccomp((string) $prepaidShipping, '0', 2) > 0;
    }

    /** Pre-validate endpoint: the same checks placement runs on the file (required, real type, not reused) but nothing is stored. */
    public function precheckCheckoutProof(array $quote, ?UploadedFile $file): void
    {
        if (! $this->offlineAtCheckout($quote['method'], $quote['prepay'], $quote['totals']->prepaidShipping)) {
            return;
        }
        if (! $file) {
            throw ValidationException::withMessages(['proof' => __('orders.proof_required')]);
        }
        app(OfflineCheckoutThrottle::class)->attemptValidate(request()); // decoding files + the reuse oracle are bounded per IP/tenant
        $this->files->assertNotReused($this->files->inspect($file)['hash']);
    }

    public function uploadProof(Order $order, UploadedFile $file): Order
    {
        if (! $this->canUploadProof($order)) {
            throw ValidationException::withMessages(['proof' => __('orders.proof_not_allowed')]);
        }
        $info = $this->files->inspect($file);
        $this->files->assertNotReused($info['hash'], $order->id);
        $old = $order->payment_proof;
        $path = $this->files->store($file, $order->tenant_id, $info);
        try {
            $order->update(['payment_proof' => $path, 'payment_proof_hash' => $info['hash'], 'proof_status' => ProofStatus::PENDING, 'payment_status' => PaymentStatus::REVIEW]);
        } catch (\Throwable $e) {
            $this->files->delete($path);
            if (PaymentProofFiles::isReuseViolation($e)) {
                $this->files->reused();
            }
            throw $e;
        }
        if ($old && $old !== $path) {
            $this->files->delete($old);
        }
        event(new OrderPaymentProofUploaded($order));

        return $order;
    }

    /** Streams the proof from the private disk. Callers MUST have authorised the request (tenant admin perm / order token). */
    public function proofResponse(Order $order): Response
    {
        abort_unless($order->payment_proof && Storage::disk(self::PROOF_DISK)->exists($order->payment_proof), 404);

        return Storage::disk(self::PROOF_DISK)->response($order->payment_proof, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** Proof approved => money received (full total, or just the prepaid shipping for COD). The order is NOT confirmed here: an admin confirms. */
    public function approveProof(Order $order): Order
    {
        $this->ensureReview($order);
        $order->update([
            'proof_status' => ProofStatus::APPROVED, 'paid_amount' => $order->payableNow(),
            'payment_status' => $order->prepay_method_id ? PaymentStatus::PARTIALLY_PAID : PaymentStatus::PAID,
        ]);
        $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => __('orders.proof_approved_note'), 'user_id' => auth('tenant')->id()]);
        event(new OrderPaymentUpdated($order, 'proof_approved'));

        return $order->refresh();
    }

    public function rejectProof(Order $order, ?string $reason = null): Order
    {
        $this->ensureReview($order);
        $order->update(['proof_status' => ProofStatus::REJECTED, 'payment_status' => PaymentStatus::UNPAID]);
        $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => trim(__('orders.proof_rejected_note').' '.$reason), 'user_id' => auth('tenant')->id()]);
        event(new OrderPaymentUpdated($order, 'proof_rejected'));

        return $order;
    }

    // ---------------------------------------------------------------- online gateway

    /** Creates a (new) payment link at the provider. Null when the order is not payable online or the provider failed (order stays pending, customer can retry). */
    public function startOnline(Order $order): ?PaymentResult
    {
        $method = $order->payingMethod();
        if (! $method || $method->type !== PaymentMethodType::ONLINE || ! $method->provider->implemented() || ! $this->payable($order)) {
            return null;
        }
        $attempt = (int) $order->payment_attempts;
        if ($attempt >= self::MAX_PAYMENT_ATTEMPTS) {
            throw ValidationException::withMessages(['payment' => __('orders.payment_attempts_exceeded')]);
        }
        $reference = $attempt === 0 ? $order->number : $order->number.'-R'.($attempt + 1); // providers reject duplicate merchant refs
        $order->update(['payment_attempts' => $attempt + 1]); // count the attempt up front: a half-done attempt may already own the merchant ref at the provider
        try {
            $payment = $this->gateways->make($method)->createPayment(new PaymentIntentData(
                $reference, $order->payableNow(), (string) $order->currency,
                ['name' => $order->customer_name, 'email' => $order->customer_email, 'phone' => $order->customer_phone],
                tenant_url(current_tenant(), '/'.app()->getLocale().'/payments/return/'.$order->public_token),
                tenant_url(current_tenant(), '/webhooks/payments/'.$method->id),
            ));
        } catch (GatewayException|GatewayNotImplementedException $e) {
            Log::warning('Payment start failed', ['order' => $order->number, 'provider' => $method->provider->value, 'error' => $e->getMessage()]);

            return null;
        }
        $order->update(['gateway_reference' => $payment->gatewayReference]);

        return $payment;
    }

    public function payable(Order $order): bool
    {
        return in_array($order->payment_status, [PaymentStatus::UNPAID, PaymentStatus::FAILED], true)
            && ! in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true);
    }

    /**
     * Provider webhook -> order. Verified (provider scheme), cross-checked against OUR order, processed once per provider event.
     *
     * @return array{0:int,1:array} [http status, json body]
     */
    public function handleWebhook(PaymentMethod $pm, Request $request): array
    {
        if (! $pm->provider->implemented()) {
            return [501, ['success' => false, 'message' => 'not implemented']];
        }
        $gateway = $this->gateways->make($pm);
        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Payment webhook signature rejected', ['method' => $pm->id, 'provider' => $pm->provider->value]);

            return [400, ['success' => false, 'message' => 'invalid signature']];
        }

        $number = $gateway->orderReference($request);
        $order = $number ? Order::where('number', $number)->where(fn ($q) => $q->where('payment_method_id', $pm->id)->orWhere('prepay_method_id', $pm->id))->first() : null;
        if (! $order) {
            return [200, ['success' => true, 'ignored' => true]]; // unknown reference: ack so the provider stops retrying
        }

        try {
            $result = $gateway->parseWebhook($request, $order, $order->payableNow());
        } catch (PaymentMismatchException $e) {
            Log::warning('Payment webhook mismatch', ['order' => $order->number, 'provider' => $pm->provider->value, 'error' => $e->getMessage()]);

            return [422, ['success' => false, 'message' => 'mismatch']];
        } catch (GatewayException $e) {
            Log::warning('Payment webhook re-query failed', ['order' => $order->number, 'error' => $e->getMessage()]);

            return [503, ['success' => false, 'message' => 'retry later']]; // provider will retry
        }

        $duplicate = DB::transaction(function () use ($pm, $order, $result) {
            $inserted = DB::table('payment_events')->insertOrIgnore([
                'tenant_id' => $order->tenant_id, 'provider' => $pm->provider->value, 'event_id' => (string) $result->eventId, 'payment_method_id' => $pm->id,
                'order_id' => $order->id, 'status' => $result->status, 'outcome' => in_array($result->status, ['pending', 'refunded'], true) ? 'ignored' : 'applied',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) {
                return true;
            }
            $this->settleGateway(Order::whereKey($order->id)->lockForUpdate()->first(), $result);

            return false;
        });

        return [200, ['success' => true] + ($duplicate ? ['duplicate' => true] : [])];
    }

    /** Result of a VERIFIED, cross-checked provider event -> order. Idempotent per state. */
    public function settleGateway(Order $order, PaymentResult $result): Order
    {
        $alreadyPaid = in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_PAID], true);
        if ($result->status === 'paid' && ! $alreadyPaid) {
            $order->update([
                'payment_status' => $order->prepay_method_id ? PaymentStatus::PARTIALLY_PAID : PaymentStatus::PAID,
                'paid_amount' => $order->payableNow(), 'gateway_reference' => $result->gatewayReference ?: $order->gateway_reference,
            ]);
            if ($order->status === OrderStatus::CANCELLED) {
                $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => __('orders.paid_after_cancel_note')]);
            } elseif ($order->status === OrderStatus::PENDING && ! $order->prepay_method_id && ($order->tenant->settings['auto_confirm_paid_online'] ?? false)) {
                $this->status->change($order, OrderStatus::CONFIRMED, __('orders.gateway_paid_note'), null);
            } else {
                $order->histories()->create(['from_status' => $order->status->value, 'to_status' => $order->status->value, 'note' => __('orders.gateway_paid_note')]);
            }
            event(new OrderPaymentUpdated($order, 'paid'));
        } elseif (in_array($result->status, ['failed', 'cancelled'], true) && ! $alreadyPaid) {
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
