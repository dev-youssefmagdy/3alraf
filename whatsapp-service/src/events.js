'use strict';
const crypto = require('crypto');
const cfg = require('./config');

const MAX_QUEUE = 2000;
const queues = new Map(); // sessionId -> { chain, size } ; events of one session are delivered in order

async function post(body) {
  const ts = String(Math.floor(Date.now() / 1000));
  const sig = crypto.createHmac('sha256', cfg.callbackSecret).update(`${ts}.${body}`).digest('hex'); // re-signed per attempt: stays inside Laravel's 5-min window
  for (let i = 0; i < 4; i++) {
    try {
      const r = await fetch(cfg.callbackUrl, {
        method: 'POST',
        headers: { 'content-type': 'application/json', 'x-wa-timestamp': ts, 'x-wa-signature': sig },
        body,
        signal: AbortSignal.timeout(8000),
      });
      if (r.ok || (r.status >= 400 && r.status < 500 && r.status !== 429 && r.status !== 408)) return; // 4xx is permanent: don't hammer
    } catch {}
    await new Promise((r) => setTimeout(r, 500 * (i + 1)));
  }
}

/**
 * Node -> Laravel callback. HMAC-signed; ordered per session (message.in before its message.status), retried briefly, never thrown.
 * Bodies are never logged. Envelope: {session_id, type, data, at}. Laravel dedupes by (owner, wa message id).
 */
function emit(sessionId, type, payload) {
  const body = JSON.stringify({ session_id: sessionId, type, data: payload, at: new Date().toISOString() });
  const q = queues.get(sessionId) || { chain: Promise.resolve(), size: 0 };
  queues.set(sessionId, q);
  if (q.size >= MAX_QUEUE) return; // Laravel unreachable for a long time: shed load rather than grow without bound
  q.size++;
  q.chain = q.chain.then(() => post(body)).catch(() => {}).finally(() => q.size--);
}

module.exports = { emit };
