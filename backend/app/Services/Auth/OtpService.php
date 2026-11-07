<?php

namespace App\Services\Auth;

use App\Models\AdminOtp;
use App\Models\Admin;
use App\Services\Whatsapp\WhatsappService;
use App\Services\Whatsapp\WhatsappUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Admin login OTPs delivered through the shop's WhatsApp session. All limits come from AuthSettings. */
class OtpService
{
    private const GUARD = 'admin';

    public function __construct(private WhatsappService $wa) {}

    private function active(int $adminId): ?AdminOtp
    {
        return AdminOtp::where(['guard' => self::GUARD, 'admin_id' => $adminId])->whereNull('consumed_at')
            ->where('expires_at', '>', now())->latest('id')->first();
    }

    /** Seconds until the next resend is allowed (0 = now). */
    public function cooldown(Admin $a): int
    {
        $otp = $this->active($a->getKey());

        if (! $otp) {
            return 0;
        }

        return max(0, $otp->last_sent_at->timestamp + (int) AuthSettings::for()->get('otp.resend_cooldown_seconds') - now()->timestamp);
    }

    /**
     * @throws OtpRefused when cooling down or out of resends
     * @throws WhatsappUnavailable when WhatsApp cannot deliver
     */
    public function issue(Admin $admin, ?string $ip): void
    {
        $set = AuthSettings::for();
        $otp = $this->active($admin->getKey());

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

        // fail before persisting: a failed delivery burns nothing. $admin->phone is E.164 digits (country code included).
        $this->wa->sendText($admin->phone, $text);

        $fields = ['code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds($ttl), 'last_sent_at' => now(), 'ip' => $ip];
        $otp
            ? $otp->update($fields + ['resend_count' => $otp->resend_count + 1]) // attempts deliberately NOT reset: resending cannot buy more guesses
            : AdminOtp::create($fields + ['guard' => self::GUARD, 'admin_id' => $admin->getKey()]);
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public function verify(Admin $admin, string $code): string
    {
        $otp = $this->active($admin->getKey());
        if (! $otp) {
            return 'expired';
        }
        $max = (int) AuthSettings::for()->get('otp.max_attempts');
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
