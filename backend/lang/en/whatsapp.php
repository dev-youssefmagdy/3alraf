<?php

return [
    'title' => 'WhatsApp connection',
    'subtitle' => 'Link a WhatsApp account to deliver login codes and notifications.',
    'status' => ['disconnected' => 'Disconnected', 'connecting' => 'Connecting…', 'qr' => 'Waiting for QR scan', 'connected' => 'Connected', 'logged_out' => 'Logged out', 'error' => 'Error'],
    'connected_number' => 'Connected number', 'last_connected' => 'Last connected', 'never' => 'Never', 'last_error' => 'Last error',
    'connect' => 'Generate QR code', 'reconnect' => 'Reconnect', 'disconnect' => 'Disconnect', 'disconnect_confirm' => 'Disconnect this WhatsApp account? You will need to scan a new QR code.',
    'connecting' => 'Starting the connection…', 'disconnected' => 'WhatsApp disconnected.',
    'scan_title' => 'Scan with WhatsApp', 'scan_help' => 'On your phone open WhatsApp → Settings → Linked devices → Link a device, then scan this code.',
    'qr_waiting' => 'Generating QR code…',
    'logs' => 'Connection log', 'no_logs' => 'No events yet.',
    'troubleshooting' => 'Troubleshooting',
    'tips' => [
        'The QR code refreshes automatically; if it expires, press Reconnect.',
        'If the status stays on Error, the WhatsApp service may be offline: contact your administrator.',
        'Logging out from the phone (Linked devices) disconnects this account; scan a new QR code to link it again.',
        'Login codes are only sent while the status is Connected.',
    ],
    'settings' => 'Login and OTP settings', 'save' => 'Save settings', 'message_needs_code' => 'The message must contain {code}.',
    'fields' => [
        'country' => 'Country', 'phone' => 'Phone number', 'password' => 'Password', 'code' => 'Verification code',
        'whatsapp_enabled' => 'WhatsApp enabled', 'otp_length' => 'Code length', 'otp_ttl_seconds' => 'Code lifetime (seconds)',
        'otp_resend_cooldown_seconds' => 'Resend cooldown (seconds)', 'otp_max_attempts' => 'Maximum verification attempts', 'otp_max_resends' => 'Maximum resends',
        'otp_message' => 'Message ({code} and {minutes} are replaced)', 'login_max_failures' => 'Failed logins before lockout', 'login_lockout_seconds' => 'Lockout (seconds)',
    ],
    'login_title' => 'Sign in', 'login_help' => 'Enter your phone number and password.', 'otp_title' => 'Verification code',
    'otp_help' => 'We sent a code to :phone on WhatsApp.', 'verify' => 'Verify', 'resend' => 'Resend code', 'resend_in' => 'Resend in :seconds s', 'back' => 'Back to login',
    'otp_resent' => 'A new code was sent.',
    'otp_invalid' => 'The code is incorrect.', 'otp_expired' => 'The code expired. Please sign in again.', 'otp_locked' => 'Too many wrong attempts. Please sign in again later.',
    'otp_wait' => 'Please wait :seconds seconds before requesting another code.', 'otp_limit' => 'Too many codes requested. Try again in :seconds seconds.',
    'otp_undeliverable' => 'We could not send the verification code through WhatsApp. Please try again later or contact your administrator.',
];
