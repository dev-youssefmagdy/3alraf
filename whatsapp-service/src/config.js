// Infrastructure settings only (port, secrets, paths). Business settings (OTP rules, enabled flags) live in Laravel's database.
'use strict';

const need = (k) => {
  const v = process.env[k];
  if (!v) throw new Error(`Missing required env ${k}`);
  return v;
};

module.exports = {
  port: Number(process.env.WA_PORT || 3100),
  host: process.env.WA_HOST || '127.0.0.1',
  apiToken: need('WA_API_TOKEN'), // Laravel -> Node bearer token
  callbackUrl: need('WA_CALLBACK_URL'), // Node -> Laravel events endpoint
  callbackSecret: need('WA_CALLBACK_SECRET'), // HMAC key for Node -> Laravel events
  authRoot: need('WA_AUTH_DIR'), // persistent dir, NOT /tmp
  encryptionKey: Buffer.from(need('WA_ENCRYPTION_KEY'), 'base64'), // 32 bytes, base64
  logLevel: process.env.WA_LOG_LEVEL || 'info',
};
