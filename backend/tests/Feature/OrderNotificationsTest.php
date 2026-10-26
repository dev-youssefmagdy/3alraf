<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType;
use App\Events\OrderReturnUpdated;
use App\Models\Branch;
use App\Models\CentralAdmin;
use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Notifications\CustomerOrderNotification;
use App\Notifications\OrderNotification;
use App\Notifications\TenantNotification;
use App\Services\ActivityTimeline;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'p1', 'sku' => 'P1', 'barcode' => '2000000000008']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, StockMovementType::PURCHASE_IN, 10, '60.00');
    $this->place = function (array $o = []) {
        $d = new CheckoutData(...array_merge([
            'proof' => testProof(), 'name' => 'Ali', 'phone' => '01001234567', 'email' => 'ali@example.com', 'cityId' => City::first()->id, 'address' => 'Street 1',
            'shippingCompanyId' => ShippingCompany::first()->id, 'paymentMethodId' => PaymentMethod::where('type', 'cod')->first()->id,
        ], $o));

        return app(OrderPlacementService::class)->place($d, [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 2]])['order'];
    };
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->clerk = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Clerk', 'email' => 'clerk@egy.test', 'password' => 'secret-123', 'is_active' => true]);
    $this->clerk->givePermissionTo('orders.view');
});

it('notifies admins in DB + realtime, and by e-mail only those holding the permission', function () {
    Notification::fake();
    $order = ($this->place)();

    Notification::assertSentTo($this->owner, OrderNotification::class, fn ($n, $ch) => $ch === ['database', 'broadcast', 'mail']);
    Notification::assertSentTo($this->clerk, OrderNotification::class, fn ($n, $ch) => $ch === ['database', 'broadcast']);
    expect($order->locale)->toBe(app()->getLocale());
});

it('does not e-mail the staff member who made the change, but still pushes to the bell', function () {
    $order = ($this->place)();
    Notification::fake();
    $this->actingAs($this->owner, 'tenant');
    app(ReturnService::class); // actor resolved from auth('tenant')
    event(new OrderReturnUpdated(OrderReturn::create(['order_id' => $order->id, 'number' => 'RET-1', 'status' => 'requested', 'return_value' => 0, 'returned_cogs' => 0, 'return_shipping_cost' => 0]), 'requested'));

    Notification::assertSentTo($this->owner, OrderNotification::class, fn ($n, $ch) => $ch === ['database', 'broadcast']);
});

it('e-mails the customer on placement, status change, payment and return - and respects the tenant switches', function () {
    Notification::fake();
    $order = ($this->place)();
    Notification::assertSentOnDemand(CustomerOrderNotification::class, fn ($n, $ch, $notifiable) => array_key_exists('ali@example.com', (array) $notifiable->routes['mail']));

    app(OrderStatusService::class)->change($order, S::CONFIRMED);
    Notification::assertSentOnDemandTimes(CustomerOrderNotification::class, 2);

    // switches
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['notifications' => ['placed' => false, 'status' => false, 'payment' => true, 'return' => true]])]);
    Notification::fake();
    $o2 = ($this->place)();
    app(OrderStatusService::class)->change($o2, S::CONFIRMED);
    Notification::assertSentOnDemandTimes(CustomerOrderNotification::class, 0);

    // no address on the order -> nothing to send
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['notifications' => ['placed' => true, 'status' => true]])]);
    Notification::fake();
    ($this->place)(['email' => null]);
    Notification::assertNotSentTo($this->owner, CustomerOrderNotification::class);
    Notification::assertSentOnDemandTimes(CustomerOrderNotification::class, 0);
});

it('renders the customer mail in the language the customer ordered in', function () {
    app()->setLocale('ar');
    $order = ($this->place)();
    app()->setLocale('en');
    $mail = (new CustomerOrderNotification($order->fresh(), 'placed'))->toMail(new AnonymousNotifiable);
    expect($order->fresh()->locale)->toBe('ar');
    app()->setLocale('ar');
    expect($mail->subject)->toContain($order->number)->and($mail->actionUrl)->toContain('/order/'.$order->public_token);
});

it('audits notifications sent, customers (masked PII) and returns', function () {
    $order = ($this->place)();
    app(OrderStatusService::class)->change($order, S::CONFIRMED);

    $log = Activity::where('log_name', 'notification')->where('subject_id', $order->id)->get();
    expect($log->pluck('description')->all())->toContain('customer.placed.mail', 'staff.new_order.database');
    $cust = $log->firstWhere('description', 'customer.placed.mail');
    expect($cust->properties['to'])->toBe('a***@example.com')->and($cust->tenant_id)->toBe($this->tenant->id);

    $c = Customer::create(['name' => 'Mona Hassan', 'email' => 'mona@example.com', 'phone' => '01111111111', 'password' => 'secret-123', 'is_active' => true]);
    $c->update(['is_active' => false, 'phone' => '01222222222']);
    $rows = Activity::where('subject_type', Customer::class)->where('subject_id', $c->id)->get();
    expect($rows)->toHaveCount(2);
    expect(json_encode($rows->map->properties))->not->toContain('mona@example.com')->not->toContain('01111111111')->not->toContain('secret');

    $ret = OrderReturn::create(['order_id' => $order->id, 'number' => 'RET-9', 'status' => 'requested', 'return_value' => 0, 'returned_cogs' => 0, 'return_shipping_cost' => 0]);
    expect(Activity::where('subject_type', OrderReturn::class)->where('subject_id', $ret->id)->exists())->toBeTrue();

    $tl = app(ActivityTimeline::class)->forOrder($order);
    expect($tl->pluck('log_name')->unique()->all())->toContain('order', 'notification', 'orderreturn');
});

it('shows the activity card to staff with audit.view and bells central admins', function () {
    $order = ($this->place)();
    $this->actingAs($this->owner, 'tenant');
    $this->get("http://egy.3alraf.test/admin/orders/{$order->id}")->assertOk()->assertSee(__('orders.activity'));

    $central = CentralAdmin::first();
    $central->notify(new TenantNotification($this->tenant, 'tenant_created'));
    $this->actingAs($central, 'central');
    $this->getJson('http://3alraf.test/admin/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', __('notifications.admin.tenant_created', ['name' => $this->tenant->name]));
});
