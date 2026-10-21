<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CustomerAuthController extends Controller
{
    public function __construct(private CustomerAccountService $accounts) {}

    public function show()
    {
        return view('storefront.register');
    }

    public function validateForm(CustomerRegisterRequest $r): JsonResponse
    {
        return $this->ok();
    }

    public function store(CustomerRegisterRequest $r): JsonResponse
    {
        $customer = $this->accounts->register($r->safe()->except('password_confirmation'));
        Auth::guard('web')->login($customer);
        $r->session()->regenerate();

        return $this->ok(__('admin.welcome_back'), route('storefront.account.index'));
    }
}
