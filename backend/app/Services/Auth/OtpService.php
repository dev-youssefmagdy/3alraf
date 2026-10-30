<?php

namespace App\Services\Auth;

use App\Models\AdminOtp;
use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use App\Services\Whatsapp\WhatsappService;
use App\Services\Whatsapp\WhatsappUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Admin login OTPs delivered through the admin's OWN owner session (central, or that tenant). All limits come from AuthSettings. */
class OtpService
{
    public function __construct(private WhatsappService $wa) {}

    private function tenantId(CentralAdmin|TenantAdmin $a): ?int
    {
        return $a instanceof TenantAdmin ? (int) $a->tenant_id : null;
    }

    private function guard(CentralAdmin|TenantAdmin $a): string
    {
        return $a instanceof TenantAdmin ? 'tenant' : 'central';
    }

    private function active(string $guard, int $adminId, ?int $tenantId): ?AdminOtp
    {
        return AdminOtp::where(['guard' => $guard, 'admin_id' => $adminId])->where('tenant_id', $tenantId)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->latest('id')->first();
    }

    /** Seconds until the next resend is allowed (0 = now). */
    public function cooldown(CentralAdmin|TenantAdmin $a): int
    {
        $otp = $this->active($this->guard($a), $a->getKey(), $this->tenantId($a));

        if (! $otp) {
            return 0;
        }

        return max(0, $otp->last_sent_at->timestamp + (int) AuthSettings::for($this->tenantId($a))->get('otp.resend_cooldown_seconds') - now()->timestamp);
    }

    /**
     * @throws OtpRefused when cooling down or out of resends
     * @throws WhatsappUnavailable when the owner's WhatsApp cannot deliver
     */
    public function issue(CentralAdmin|TenantAdmin $admin, ?string $ip): void
    {
        $tenantId = $this->tenantId($admin);
        $guard = $this->guard($admin);
        $set = AuthSettings::for($tenantId);
        $otp = $this->active($guard, $admin->getKey(), $tenantId);

        if ($otp) {
            if (($wait = $this->cooldown($admin)) > 0) {
                throw new OtpRefused('cooldown', $wait);
            }
            if ($otp->resend_count >= $set->get('otp.max_resends')) {
                throw new OtpRefused('resend_limit', max(1, $otp->expires_at->timestamp - now()->timestamp));
            }
        }

        $length = max(4, min(10, (int) $set->get('otp.length')));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) $set->get('otp.ttl_seconds');
        $text = strtr((string) $set->get('otp.message'), ['{code}' => $code, '{minutes}' => (string) max(1, (int) ceil($ttl / 60))]);

        $this->wa->sendText($tenantId, $admin->phone, $text); // fail before persisting: a failed delivery burns nothing

        $fields = ['code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds($ttl), 'last_sent_at' => now(), 'ip' => $ip];
        $otp
            ? $otp->update($fields + ['resend_count' => $otp->resend_count + 1]) // attempts deliberately NOT reset: resending cannot buy more guesses
            : AdminOtp::create($fields + ['guard' => $guard, 'admin_id' => $admin->getKey(), 'tenant_id' => $tenantId]);
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public function verify(CentralAdmin|TenantAdmin $admin, string $code): string
    {
        $tenantId = $this->tenantId($admin);
        $otp = $this->active($this->guard($admin), $admin->getKey(), $tenantId);
        if (! $otp) {
            return 'expired';
        }
        $max = (int) AuthSettings::for($tenantId)->get('otp.max_attempts');
        if ($otp->attempts >= $max) {
            return 'locked';
        }
        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return $otp->attempts >= $max ? 'locked' : 'invalid'; // increment() already refreshed the in-memory count
        }

        // single use: only one concurrent request can flip consumed_at
        return DB::table('admin_otps')->where('id', $otp->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1 ? 'ok' : 'expired';
    }
}
