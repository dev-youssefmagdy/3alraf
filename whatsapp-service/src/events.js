'use strict';
const crypto = require('crypto');
const cfg = require('./config');

/** Node -> Laravel callback. HMAC-signed so only this service can post events; failures are retried briefly, never thrown. */
async function emit(sessionId, type, payload) {
  const body = JSON.stringify({ session_id: sessionId, type, data: payload, at: new Date().toISOString() });
  const ts = String(Math.floor(Date.now() / 1000));
  const sig = crypto.createHmac('sha256', cfg.callbackSecret).update(`${ts}.${body}`).digest('hex');
  for (let i = 0; i < 3; i++) {
    try {
      const r = await fetch(cfg.callbackUrl, {
        method: 'POST',
        headers: { 'content-type': 'application/json', 'x-wa-timestamp': ts, 'x-wa-signature': sig },
        body,
        signal: AbortSignal.timeout(5000),
      });
      if (r.ok) return;
    } catch {}
    await new Promise((r) => setTimeout(r, 500 * (i + 1)));
  }
}

module.exports = { emit: (...a) => void emit(...a) };
