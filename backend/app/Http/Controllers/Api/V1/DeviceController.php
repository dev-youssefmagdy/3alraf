<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\DeviceRequest;
use App\Services\Push\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Push token registry (FCM). Guests may register; a signed-in customer's token binds the device to the account. */
class DeviceController extends ApiController
{
    public function store(DeviceRequest $r, DeviceService $devices): JsonResponse
    {
        $d = $devices->register($r->validated(), $r->user());

        return $this->respond(['id' => $d->id, 'platform' => $d->platform, 'locale' => $d->locale, 'bound_to_customer' => $d->customer_id !== null], __('admin.saved'), status: 201);
    }

    /** DELETE devices {token}: call on logout / push opt-out. Idempotent. */
    public function destroy(Request $r, DeviceService $devices): JsonResponse
    {
        $r->validate(['token' => ['required', 'string', 'max:512']]);
        $devices->unregister((string) $r->input('token'));

        return $this->respond(null, __('admin.deleted'));
    }
}
