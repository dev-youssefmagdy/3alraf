<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\OrderDataTable;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OrderReturnRequest;
use App\Http\Requests\Tenant\OrderStatusRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\ActivityTimeline;
use App\Services\Orders\InvoicePdf;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OrderController extends Controller implements HasMiddleware
{
    public function __construct(private OrderRepositoryInterface $orders, private OrderStatusService $status, private OrderPaymentService $payments, private ReturnService $returns) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:orders.view', only: ['index', 'datatable', 'show', 'invoice', 'invoicePdf', 'proofFile']),
            new Middleware('can:orders.edit', only: ['changeStatus', 'validateStatus']),
            new Middleware('can:orders.payments', only: ['approveProof', 'rejectProof']),
            new Middleware('can:returns.create', only: ['createReturn', 'validateReturn']),
        ];
    }

    public function index()
    {
        return view('tenant.orders.index', [
            'table' => new OrderDataTable, 'statuses' => OrderStatus::options(), 'paymentStatuses' => PaymentStatus::options(),
            'methods' => PaymentMethod::get()->mapWithKeys(fn ($m) => [$m->id => $m->name])->all(),
        ]);
    }

    public function datatable(): JsonResponse
    {
        return (new OrderDataTable)->ajax();
    }

    public function show(int $id)
    {
        $order = $this->find($id);

        // other orders (any status) of this tenant that carry the very same proof file: a classic double-spend of one transfer receipt
        $sharedProof = $order->payment_proof_hash
            ? Order::query()->where('payment_proof_hash', $order->payment_proof_hash)->where('id', '!=', $order->id)->orderBy('id')->limit(10)->get(['id', 'number', 'status', 'proof_status'])
            : collect();

        $activities = auth('tenant')->user()->can('audit.view') ? app(ActivityTimeline::class)->forOrder($order) : null;

        return view('tenant.orders.show', ['activities' => $activities, 'sharedProof' => $sharedProof, 'order' => $order, 'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()]);
    }

    public function invoice(int $id)
    {
        return view('tenant.orders.invoice', ['order' => $this->find($id), 'public' => false]);
    }

    public function invoicePdf(int $id, InvoicePdf $pdf)
    {
        $order = $this->find($id);

        return $pdf->render($order)->download($pdf->filename($order));
    }

    /** Payment proof (private disk) for tenant admins with orders.view; the query is tenant-scoped. */
    public function proofFile(int $id)
    {
        return $this->payments->proofResponse($this->find($id));
    }

    public function validateStatus(OrderStatusRequest $r, int $id): JsonResponse
    {
        return $this->ok();
    }

    public function changeStatus(OrderStatusRequest $r, int $id): JsonResponse
    {
        $this->status->change($this->find($id), OrderStatus::from($r->input('status')), $r->input('note'));

        return $this->ok(__('orders.status_updated'), route('tenant.orders.show', $id));
    }

    public function approveProof(int $id): JsonResponse
    {
        $this->payments->approveProof($this->find($id));

        return $this->ok(__('orders.proof_approved'), route('tenant.orders.show', $id));
    }

    public function rejectProof(Request $r, int $id): JsonResponse
    {
        $this->payments->rejectProof($this->find($id), $r->input('reason'));

        return $this->ok(__('orders.proof_rejected'), route('tenant.orders.show', $id));
    }

    public function validateReturn(OrderReturnRequest $r, int $id): JsonResponse
    {
        return $this->ok();
    }

    public function createReturn(OrderReturnRequest $r, int $id): JsonResponse
    {
        $ret = $this->returns->request($this->find($id), ReturnRequestData::fromArray($r->validated() + ['restock' => $r->boolean('restock'), 'refund_shipping' => $r->boolean('refund_shipping')]));

        return $this->ok(__('orders.return_requested'), route('tenant.returns.show', $ret->id));
    }

    private function find(int $id)
    {
        return $this->orders->query()->with(['items', 'histories.user', 'customer', 'city', 'branch', 'shippingCompany', 'paymentMethod', 'discountCode', 'returns'])->findOrFail($id);
    }
}
