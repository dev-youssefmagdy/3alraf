<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Customer "Continue with Google" (storefront web). Three hops on the one host:
 *   GET {locale}/auth/google           signed 5-min single-use state (locale, intended, nonce kept in the session) -> Google
 *   GET /auth/google/callback          verifies + consumes the state, reads the Google profile, -> complete with a signed 60-s single-use handoff
 *   GET {locale}/auth/google/complete  verifies handoff (nonce = this browser), logs the customer in, redirects to the intended URL
 * The Google access token never leaves the callback request; only (sub, e-mail, verified flag, name) travel in the handoff.
 * The callback URI stays /auth/google/callback (outside the locale prefix) so the redirect URI registered at Google keeps working.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private GoogleAuthService $google) {}

    public function redirect(Request $request, string $locale)
    {
        abort_unless($this->google->enabled(), 404);

        $intended = $this->google->safePath((string) $request->query('intended'), $request->getHost())
            ?? $this->google->safePath((string) $request->session()->get('url.intended'), $request->getHost());
        $nonce = bin2hex(random_bytes(16));
        $request->session()->put('google_nonce', $nonce);

        $state = $this->google->sign('state', ['l' => $locale, 'i' => $intended, 'n' => $nonce], GoogleAuthService::STATE_TTL);

        return $this->driver()->with(['state' => $state, 'prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless($this->google->enabled(), 404);
        $state = $this->google->verify('state', (string) $request->query('state'));
        // forged / expired / replayed state: nobody to send back to
        abort_if($state === null || ! $this->google->consume('state', $state), 400, __('store.google.invalid_state'));

        $claims = ['l' => $state['l'], 'i' => $state['i'], 'n' => $state['n']];
        $user = null;
        if (! $request->query('error') && $request->query('code')) {
            try {
                $user = $this->driver()->user();
            } catch (Throwable $e) {
                Log::warning('google login: token/profile exchange failed', ['error' => class_basename($e)]);
            }
        }
        if (! $user || ! $user->getId()) {
            $claims['err'] = $request->query('error') ? 'denied' : 'failed';
        } else {
            $raw = (array) ($user->user ?? []);
            $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;
            $claims += [
                'sub' => (string) $user->getId(), 'email' => $user->getEmail(), 'ev' => filter_var($verified, FILTER_VALIDATE_BOOLEAN),
                'name' => $user->getName(), 'avatar' => $user->getAvatar(),
            ];
        }
        $token = $this->google->sign('handoff', $claims, GoogleAuthService::HANDOFF_TTL);

        return redirect()->to(url($state['l'].'/auth/google/complete').'?token='.$token);
    }

    public function complete(Request $request, string $locale)
    {
        abort_unless($this->google->enabled(), 404);
        $data = $this->google->verify('handoff', (string) $request->query('token'));
        // tampered, expired, replayed or started in another browser: refuse
        $nonce = (string) $request->session()->pull('google_nonce');
        if ($data === null || ($data['l'] ?? null) !== $locale
            || $nonce === '' || ! hash_equals($nonce, (string) ($data['n'] ?? '')) || ! $this->google->consume('handoff', $data)) {
            return $this->fail('invalid_state');
        }
        if (isset($data['err'])) {
            return $this->fail($data['err']);
        }

        [$customer, $error] = $this->google->resolve([
            'sub' => $data['sub'] ?? '', 'email' => $data['email'] ?? null, 'email_verified' => (bool) ($data['ev'] ?? false),
            'name' => $data['name'] ?? null, 'avatar' => $data['avatar'] ?? null,
        ]);
        if (! $customer) {
            Log::info('google login refused', ['reason' => $error]);

            return $this->fail($error);
        }

        Auth::guard('web')->login($customer);
        $request->session()->regenerate(); // guest cart token survives; CartService merges it into the customer's cart on the next cart read
        $request->session()->forget('url.intended');
        $path = $this->google->safePath((string) ($data['i'] ?? ''), $request->getHost());

        return redirect($path ? url($path) : route('storefront.home'))->with('status', __('admin.welcome_back'));
    }

    private function fail(?string $key)
    {
        return redirect()->route('storefront.login')->with('google_error', __('store.google.'.($key ?: 'failed')));
    }

    private function driver()
    {
        config(['services.google.redirect' => $this->google->callbackUrl()]); // Socialite insists on the key; the value is always the one callback URI

        return Socialite::driver('google')->stateless()->redirectUrl($this->google->callbackUrl());
    }
}
