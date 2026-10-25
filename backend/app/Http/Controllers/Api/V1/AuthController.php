<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\VerifyEmailRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Http\Requests\Storefront\ProfileRequest;
use App\Http\Resources\Api\CustomerResource;
use App\Services\Api\ApiAuthService;
use App\Services\Api\AuthCodeService;
use App\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customer authentication (token ability `customer`). */
class AuthController extends ApiController
{
    public function __construct(private ApiAuthService $auth, private AuthCodeService $codes) {}

    public function register(CustomerRegisterRequest $r): JsonResponse
    {
        [$customer, $token] = $this->auth->registerCustomer($r->safe()->except('password_confirmation'), $r->input('device_name'));
        $this->codes->sendVerification($customer);

        return $this->respond($this->session($customer, $token), __('admin.welcome_back'), status: 201);
    }

    public function login(LoginRequest $r): JsonResponse
    {
        [$customer, $token] = $this->auth->loginCustomer($r->input('email'), $r->input('password'), $r->input('device_name'));

        return $this->respond($this->session($customer, $token), __('admin.welcome_back'));
    }

    public function logout(Request $r): JsonResponse
    {
        $this->auth->logout($r->user());

        return $this->respond(null, __('store.api.logged_out'));
    }

    public function me(Request $r): JsonResponse
    {
        return $this->respond(new CustomerResource($r->user()));
    }

    public function update(ProfileRequest $r, CustomerAccountService $accounts): JsonResponse
    {
        return $this->respond(new CustomerResource($accounts->updateProfile($r->user(), $r->safe()->except(['password_confirmation', 'current_password']))), __('admin.saved'));
    }

    public function forgotPassword(ForgotPasswordRequest $r): JsonResponse
    {
        $this->codes->sendReset($r->input('email'));

        return $this->respond(null, __('store.api.reset_sent')); // same answer whether or not the e-mail exists
    }

    public function resetPassword(ResetPasswordRequest $r): JsonResponse
    {
        $this->codes->reset($r->input('email'), $r->input('code'), $r->input('password'));

        return $this->respond(null, __('store.api.password_reset'));
    }

    public function sendVerification(Request $r): JsonResponse
    {
        $this->codes->sendVerification($r->user());

        return $this->respond(null, __('store.api.verification_sent'));
    }

    public function verifyEmail(VerifyEmailRequest $r): JsonResponse
    {
        return $this->respond(new CustomerResource($this->codes->verify($r->user(), $r->input('code'))), __('store.api.verified'));
    }

    private function session($customer, string $token): array
    {
        return ['token' => $token, 'token_type' => 'Bearer', 'abilities' => ['customer'], 'customer' => (new CustomerResource($customer))->resolve()];
    }
}
