'use strict';
const { downloadMediaMessage } = require('@whiskeysockets/baileys');
const cfg = require('./config');
const media = require('./media');
const { normalize, statusOf } = require('./normalize');

const DOWNLOAD_TIMEOUT_MS = 60_000;
const MEDIA_TYPES = new Set(['image', 'video', 'audio', 'ptt', 'document', 'sticker']);

const withTimeout = (p, ms) => {
  let t;
  const timeout = new Promise((_, rej) => (t = setTimeout(() => rej(new Error('download timeout')), ms)));
  return Promise.race([p, timeout]).finally(() => clearTimeout(t));
};

/**
 * Wire a socket's events to the Laravel callback. Event types emitted (see plan progress log for shapes):
 * message.in, message.reaction, message.edit, message.delete, media.ready, message.status, presence, contact.update.
 * `deps` allows tests to inject emit/download.
 */
function bind(sock, sessionId, deps = {}) {
  const emit = deps.emit || require('./events').emit;
  const download = deps.download || ((msg) => downloadMediaMessage(msg, 'buffer', {}, { logger: undefined, reuploadRequest: sock.updateMediaMessage }));

  async function fetchMedia(msg, env) {
    const base = { message_id: env.id, jid: env.jid, from_me: env.from_me };
    try {
      if (env.media.size && env.media.size > cfg.maxInboundMediaBytes) throw new Error('too_large');
      const buf = await withTimeout(download(msg), DOWNLOAD_TIMEOUT_MS);
      if (buf.length > cfg.maxInboundMediaBytes) throw new Error('too_large');
      const token = media.putBuffer(sessionId, buf, { mime: env.media.mime, name: env.media.file_name });
      emit(sessionId, 'media.ready', { ...base, status: 'ready', token, mime: env.media.mime, size: buf.length, sha256: media.lookup(sessionId, token).sha256 });
    } catch (e) {
      emit(sessionId, 'media.ready', { ...base, status: 'failed', reason: e.message === 'too_large' || e.message === 'download timeout' ? e.message.replace(' ', '_') : 'download_failed' });
    }
  }

  sock.ev.on('messages.upsert', ({ messages, type }) => {
    if (type !== 'notify') return;
    for (const msg of messages || []) {
      let n;
      try {
        n = normalize(msg);
      } catch {
        continue; // malformed message must never take the socket down
      }
      if (!n) continue;
      emit(sessionId, n.kind, n.data);
      if (n.kind === 'message.in' && MEDIA_TYPES.has(n.data.type) && n.data.media) void fetchMedia(msg, n.data);
    }
  });

  sock.ev.on('messages.update', (updates) => {
    for (const u of updates || []) {
      const st = statusOf(u.update?.status);
      if (!u.key?.id || !st || u.key.remoteJid?.endsWith('@g.us')) continue;
      if (u.key.fromMe) emit(sessionId, 'message.status', { id: u.key.id, jid: u.key.remoteJid, from_me: true, status: st });
    }
  });

  sock.ev.on('message-receipt.update', (items) => {
    for (const r of items || []) {
      const rc = r.receipt || {};
      const st = rc.readTimestamp ? 'read' : rc.playedTimestamp ? 'read' : rc.receiptTimestamp ? 'delivered' : null;
      if (st && r.key?.fromMe && r.key.id && !r.key.remoteJid?.endsWith('@g.us')) emit(sessionId, 'message.status', { id: r.key.id, jid: r.key.remoteJid, from_me: true, status: st });
    }
  });

  sock.ev.on('messages.reaction', (items) => {
    for (const r of items || []) {
      if (!r.key?.id) continue;
      emit(sessionId, 'message.reaction', { id: r.reaction?.key?.id || null, jid: r.key.remoteJid, from_me: !!r.reaction?.key?.fromMe, target_id: r.key.id, target_from_me: !!r.key.fromMe, emoji: r.reaction?.text || '', timestamp: Math.floor(Number(r.reaction?.senderTimestampMs || Date.now()) / 1000) });
    }
  });

  sock.ev.on('presence.update', ({ id, presences }) => {
    if (!id || id.endsWith('@g.us')) return;
    for (const [participant, p] of Object.entries(presences || {})) {
      emit(sessionId, 'presence', { jid: id, participant, state: p.lastKnownPresence || 'unavailable', last_seen: p.lastSeen || null }); // composing|recording|available|unavailable|paused
    }
  });

  const contacts = (list) => {
    const rows = (list || []).filter((c) => c.id && !c.id.endsWith('@g.us')).map((c) => ({ jid: c.id, name: c.name || null, notify: c.notify || null, verified_name: c.verifiedName || null }));
    for (let i = 0; i < rows.length; i += 100) emit(sessionId, 'contact.update', { contacts: rows.slice(i, i + 100) });
  };
  sock.ev.on('contacts.upsert', contacts);
  sock.ev.on('contacts.update', contacts);
}

module.exports = { bind };
