<?php

namespace App\Services\Api;

use App\DTOs\Orders\ReturnRequestData;
use App\Enums\PaymentMethodType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\ReturnService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Order lookups for the mobile API (customer list, guest-safe token lookup) and the "what now" hint after checkout. */
class ApiOrderService
{
    public function __construct(private OrderPaymentService $payments, private ReturnService $returns) {}

    public function paginateFor(Customer $c, int $perPage = 10): LengthAwarePaginator
    {
        return Order::with(['items', 'paymentMethod'])->where('customer_id', $c->id)->latest('id')->paginate(max(1, min(50, $perPage)));
    }

    /** Orders are addressed by their unguessable public token (same trust model as the web order page). */
    public function byToken(string $token): Order
    {
        return Order::with(['items', 'histories', 'city', 'paymentMethod', 'prepayMethod', 'shippingCompany', 'returns'])->where('public_token', $token)->firstOrFail();
    }

    public function requestReturn(Order $order, ?Customer $c, array $input): OrderReturn
    {
        abort_unless($c && $order->customer_id === $c->id, 403);

        return $this->returns->request($order, ReturnRequestData::fromArray(['items' => $input['items'], 'reason' => $input['reason'] ?? null, 'restock' => true]));
    }

    /** New payment link for an unpaid online order. */
    public function payLink(Order $order): string
    {
        $payment = $this->payments->startOnline($order);
        if (! $payment?->redirectUrl) {
            throw ValidationException::withMessages(['payment' => __('orders.payment_start_failed')]);
        }

        return $payment->redirectUrl;
    }

    /**
     * Next step for the app after placing an order (Sprint 10 rules): online -> redirect to the gateway; wallet -> upload the transfer proof;
     * COD with prepaid shipping follows the prepay method; plain COD / awaiting admin confirmation -> none.
     */
    public function nextAction(Order $order, ?string $payUrl): string
    {
        $method = $order->payingMethod();
        if ($payUrl) {
            return 'redirect_to_payment';
        }
        if ($method?->type === PaymentMethodType::ONLINE && $this->payments->payable($order)) {
            return 'retry_payment'; // gateway could not create a link; POST orders/{token}/pay
        }
        if ($this->payments->canUploadProof($order) && ! $order->payment_proof) {
            return 'upload_proof';
        }

        return 'none';
    }
}
