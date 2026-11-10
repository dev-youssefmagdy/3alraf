<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\AffiliateRegisterData;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Concerns\GuardsLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\Storefront\AffiliateRegisterRequest;
use App\Models\Affiliate;
use App\Services\Affiliates\AffiliateBonusService;
use App\Services\Affiliates\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Affiliate guard: login (approved accounts only) and registration (waits for admin approval). */
class AffiliateAuthController extends Controller
{
    use GuardsLogins;

    public function __construct(private AffiliateService $service) {}

    public function showLogin()
    {
        return view('storefront.affiliate.login');
    }

    public function validateLogin(LoginRequest $r): JsonResponse
    {
        return $this->ok();
    }

    public function login(LoginRequest $r): JsonResponse
    {
        $email = strtolower(trim($r->input('email')));
        $this->assertNotLockedOut('affiliate', $email);
        $a = Affiliate::where('email', $email)->first();
        if (! $a || ! Hash::check($r->input('password'), $a->password)) {
            $this->recordLoginFailure('affiliate', $email);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $this->clearLoginFailures('affiliate', $email);
        if ($a->status !== AffiliateStatus::APPROVED) {
            throw ValidationException::withMessages(['email' => __('growth.login_'.$a->status->value)]);
        }
        Auth::guard('affiliate')->login($a, $r->boolean('remember'));
        $r->session()->regenerate();

        return $this->ok(__('admin.welcome_back'), route('storefront.affiliate.dashboard'));
    }

    /** Public, indexable program page: the sales-bonus line and meta description use the live settings (only while the bonus is enabled). */
    public function showRegister(AffiliateBonusService $bonus)
    {
        return view('storefront.affiliate.register', ['bonus' => $bonus->enabled() && (float) $bonus->amount() > 0
            ? ['amount' => $bonus->amount(), 'threshold' => $bonus->threshold()] : null]);
    }

    public function validateRegister(AffiliateRegisterRequest $r): JsonResponse
    {
        return $this->ok();
    }

    public function register(AffiliateRegisterRequest $r): JsonResponse
    {
        $this->service->register(AffiliateRegisterData::fromRequest($r));

        return $this->ok(__('growth.registered_pending'), route('storefront.affiliate.login'));
    }

    public function logout(Request $r)
    {
        $this->signOut($r, 'affiliate');

        return redirect()->route('storefront.affiliate.login');
    }
}
