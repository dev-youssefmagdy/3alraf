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
  spoolDir: process.env.WA_SPOOL_DIR || require('path').join(require('os').tmpdir(), '3alraf-wa-spool'), // transient media hand-off (private, TTL-swept)
  mediaTtlMs: Number(process.env.WA_MEDIA_TTL_MS || 15 * 60_000),
  maxInboundMediaBytes: Number(process.env.WA_MAX_INBOUND_MEDIA || 100 * 1024 * 1024),
  maxUploadBytes: Number(process.env.WA_MAX_UPLOAD || 100 * 1024 * 1024),
  // per-type outbound caps (bytes); Laravel enforces the same limits first
  caps: { image: 16 << 20, video: 64 << 20, audio: 16 << 20, ptt: 16 << 20, sticker: 2 << 20, document: 100 << 20 },
  logLevel: process.env.WA_LOG_LEVEL || 'info',
};
