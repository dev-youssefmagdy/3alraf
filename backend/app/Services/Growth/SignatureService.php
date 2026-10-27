<?php

namespace App\Services\Growth;

/** HMAC-SHA256 over "{timestamp}.{raw body}" (both directions). */
class SignatureService
{
    public function sign(string $secret, int|string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        return hash_equals($this->sign($secret, $timestamp, $body), strtolower(trim($signature)));
    }

    public function withinWindow(string $timestamp): bool
    {
        return ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= (int) config('growth.hmac.replay_window_seconds');
    }
}
