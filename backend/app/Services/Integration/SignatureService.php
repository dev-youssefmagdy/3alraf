<?php

namespace App\Services\Integration;

/** HMAC-SHA256 (hex, lower case) over "{timestamp}.{raw body}"; the body of a GET is the empty string. */
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
        return ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= (int) config('integration.hmac.replay_window_seconds');
    }
}
