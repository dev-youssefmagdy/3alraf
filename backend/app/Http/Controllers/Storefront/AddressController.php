<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddressRequest;
use App\Services\CustomerAddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(private CustomerAddressService $service) {}

    public function index(): JsonResponse
    {
        return $this->ok(null, null, ['addresses' => $this->service->forCustomer(auth('web')->id())->toArray()]);
    }

    public function validateForm(AddressRequest $r): JsonResponse
    {
        return $this->ok();
    }

    public function store(AddressRequest $r): JsonResponse
    {
        $a = $this->service->save(auth('web')->user(), $r->validated());

        return $this->ok(__('admin.saved'), $r->boolean('inline') ? null : route('storefront.account.index'), ['address' => $a->toArray()]);
    }

    public function update(AddressRequest $r, int $id): JsonResponse
    {
        $a = $this->service->save(auth('web')->user(), $r->validated(), $id);

        return $this->ok(__('admin.saved'), $r->boolean('inline') ? null : route('storefront.account.index'), ['address' => $a->toArray()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete(auth('web')->user(), $id);

        return $this->ok(__('admin.deleted'), route('storefront.account.index'));
    }
}
