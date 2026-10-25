<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProfileRequest;
use App\Models\City;
use App\Models\Order;
use App\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;

/** Customer account page: orders, profile, addresses (address CRUD = existing JSON endpoints), wishlist link. */
class AccountController extends Controller
{
    public function __construct(private CustomerAccountService $accounts) {}

    public function index()
    {
        $c = auth('web')->user();

        return view('storefront.account', [
            'customer' => $c, 'orders' => Order::where('customer_id', $c->id)->latest('id')->paginate(10),
            'addresses' => $c->addresses()->with('city')->get(), 'cities' => City::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function validateForm(ProfileRequest $r): JsonResponse
    {
        return $this->ok();
    }

    public function update(ProfileRequest $r): JsonResponse
    {
        $this->accounts->updateProfile(auth('web')->user(), $r->safe()->except(['password_confirmation', 'current_password']));

        return $this->ok(__('admin.saved'), route('storefront.account.index'));
    }
}
