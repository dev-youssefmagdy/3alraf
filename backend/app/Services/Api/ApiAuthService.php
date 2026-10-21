<?php

namespace App\Services\Api;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Concerns\GuardsLogins;
use App\Models\Affiliate;
use App\Models\Customer;
use App\Services\CustomerAccountService;
use App\Services\Orders\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Credential checks and Sanctum token issuing for the two token types (customer / affiliate). Reuses the web login lockout. */
class ApiAuthService
{
    use GuardsLogins;

    public function __construct(private CustomerAccountService $accounts, private CartService $cart) {}

    /** @return array{0:Customer,1:string} [customer, plain token] */
    public function registerCustomer(array $data, ?string $device): array
    {
        $c = $this->accounts->register($data);
        $this->adoptGuestCart($c);

        return [$c, $this->token($c, 'customer', $device)];
    }

    /** @return array{0:Customer,1:string} */
    public function loginCustomer(string $email, string $password, ?string $device): array
    {
        $email = strtolower(trim($email));
        $this->assertNotLockedOut('customer_api', $email);
        $c = Customer::where('email', $email)->where('is_active', true)->first();
        if (! $c || ! Hash::check($password, $c->password)) {
            $this->recordLoginFailure('customer_api', $email);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $this->clearLoginFailures('customer_api', $email);
        $this->adoptGuestCart($c);

        return [$c, $this->token($c, 'customer', $device)];
    }

    /** @return array{0:Affiliate,1:string} */
    public function loginAffiliate(string $email, string $password, ?string $device): array
    {
        $email = strtolower(trim($email));
        $this->assertNotLockedOut('affiliate_api', $email);
        $a = Affiliate::where('email', $email)->first();
        if (! $a || ! Hash::check($password, $a->password)) {
            $this->recordLoginFailure('affiliate_api', $email);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $this->clearLoginFailures('affiliate_api', $email);
        if ($a->status !== AffiliateStatus::APPROVED) {
            throw ValidationException::withMessages(['email' => __('growth.login_'.$a->status->value)]);
        }

        return [$a, $this->token($a, 'affiliate', $device)];
    }

    /** Revokes the token used for this request. */
    public function logout(Customer|Affiliate $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /** The guest cart (X-Cart-Token) is merged into / becomes the customer's cart (CartService rules). */
    public function adoptGuestCart(Customer $c): void
    {
        Auth::guard('web')->setUser($c);
        $this->cart->cart(false);
    }

    private function token(Customer|Affiliate $user, string $ability, ?string $device): string
    {
        return $user->createToken(mb_substr($device ?: 'mobile', 0, 60), [$ability])->plainTextToken;
    }
}
