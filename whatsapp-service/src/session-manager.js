'use strict';
const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');
const pino = require('pino');
const baileys = require('@whiskeysockets/baileys');
const { default: makeWASocket, DisconnectReason, fetchLatestBaileysVersion, Browsers } = baileys;
const cfg = require('./config');
const { encryptedAuthState } = require('./crypto-store');
const events = require('./events');
const inbound = require('./inbound');
const media = require('./media');
const outbound = require('./outbound');

const log = pino({ level: cfg.logLevel, redact: ['creds', 'authState', '*.password', '*.text'] }); // message bodies (OTPs) never logged

// ids are `central` or `tenant-<n>`; anything else is rejected before touching the filesystem
const ID_RE = /^(central|tenant-[1-9][0-9]{0,9})$/;
const MAX_BACKOFF = 60_000;

class Session {
  constructor(id) {
    this.id = id;
    this.sock = null;
    this.status = 'disconnected'; // disconnected|connecting|qr|connected|logged_out|error
    this.qr = null;
    this.phone = null;
    this.lastError = null;
    this.retries = 0;
    this.timer = null;
    this.starting = false;
    this.wantRun = false;
    this.sendChain = Promise.resolve(); // outbound messages go out one at a time per session
  }
  dir() {
    return path.join(cfg.authRoot, this.id);
  }
  snapshot() {
    return { session_id: this.id, status: this.status, phone: this.phone, qr: this.qr, error: this.lastError };
  }
  set(status, extra = {}) {
    this.status = status;
    if (extra.error !== undefined) this.lastError = extra.error;
    events.emit(this.id, status === 'qr' ? 'qr' : 'status', this.snapshot());
  }
}

const sessions = new Map();

function get(id) {
  if (!ID_RE.test(id)) throw Object.assign(new Error('invalid session id'), { code: 400 });
  if (!sessions.has(id)) sessions.set(id, new Session(id));
  return sessions.get(id);
}

async function start(id) {
  const s = get(id);
  s.wantRun = true;
  if (s.starting || s.status === 'connected' || s.status === 'connecting' || s.status === 'qr') return s; // no duplicate sockets
  s.starting = true;
  try {
    clearTimeout(s.timer);
    const { state, saveCreds } = await encryptedAuthState(s.dir(), cfg.encryptionKey);
    const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: undefined }));
    s.set('connecting', { error: null });
    const sock = makeWASocket({
      version,
      auth: state,
      logger: log.child({ session: id, level: 'warn' }),
      browser: Browsers.appropriate('3alraf'),
      printQRInTerminal: false,
      markOnlineOnConnect: false,
      syncFullHistory: false,
    });
    s.sock = sock;
    sock.ev.on('creds.update', saveCreds);
    inbound.bind(sock, id);
    sock.ev.on('connection.update', async (u) => {
      if (s.sock !== sock) return; // stale socket
      if (u.qr) {
        s.qr = await QRCode.toDataURL(u.qr, { margin: 1, width: 320 });
        s.set('qr');
      }
      if (u.connection === 'open') {
        s.qr = null;
        s.retries = 0;
        s.phone = (sock.user?.id || '').split(':')[0].split('@')[0] || null;
        s.set('connected', { error: null });
      }
      if (u.connection === 'close') {
        const code = u.lastDisconnect?.error?.output?.statusCode;
        s.sock = null;
        s.qr = null;
        if (code === DisconnectReason.loggedOut || code === DisconnectReason.badSession) {
          s.phone = null;
          wipe(s);
          s.set('logged_out', { error: 'Logged out from the phone. Scan a new QR code.' });
          return;
        }
        s.set('disconnected', { error: u.lastDisconnect?.error?.message || 'connection closed' });
        schedule(s);
      }
    });
  } catch (e) {
    s.sock = null;
    s.set('error', { error: String(e.message || e) });
    schedule(s);
  } finally {
    s.starting = false;
  }
  return s;
}

function schedule(s) {
  if (!s.wantRun) return;
  clearTimeout(s.timer);
  const delay = Math.min(MAX_BACKOFF, 1000 * 2 ** s.retries++);
  s.timer = setTimeout(() => start(s.id).catch((e) => log.error({ err: e.message, id: s.id }, 'restart failed')), delay);
}

function wipe(s) {
  fs.rmSync(s.dir(), { recursive: true, force: true });
}

async function stop(id, { logout }) {
  const s = get(id);
  s.wantRun = false;
  clearTimeout(s.timer);
  const sock = s.sock;
  s.sock = null;
  if (sock) {
    try {
      logout ? await sock.logout() : sock.end(undefined);
    } catch (e) {
      log.warn({ id, err: e.message }, 'stop error');
    }
  }
  if (logout) wipe(s);
  s.qr = null;
  s.phone = null;
  s.set('disconnected', { error: null });
  return s;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rand = (min, max) => Math.floor(min + Math.random() * (max - min + 1));

/**
 * Messages are sent one at a time per session. In `humanize` mode (notifications) each one is preceded by a typing pause and
 * followed by a random gap (anti-ban); instant mode (login codes) skips both so OTPs are never delayed.
 */
function send(id, to, text, { humanize = false, minGapMs = 0, maxGapMs = 0 } = {}) {
  const s = get(id);
  const job = s.sendChain.then(async () => {
    if (s.status !== 'connected' || !s.sock) throw Object.assign(new Error('session not connected'), { code: 409 });
    const digits = String(to).replace(/\D/g, '');
    if (digits.length < 8 || digits.length > 15) throw Object.assign(new Error('invalid recipient'), { code: 422 });
    const [exists] = await s.sock.onWhatsApp(`${digits}@s.whatsapp.net`);
    if (!exists?.exists) throw Object.assign(new Error('number is not on WhatsApp'), { code: 422 });
    if (humanize) {
      await s.sock.presenceSubscribe(exists.jid);
      await s.sock.sendPresenceUpdate('composing', exists.jid);
      await sleep(Math.min(6000, 800 + text.length * 25));
      await s.sock.sendPresenceUpdate('paused', exists.jid);
    }
    const res = await s.sock.sendMessage(exists.jid, { text });
    if (humanize) await sleep(rand(minGapMs, Math.max(minGapMs, maxGapMs)));
    return { message_id: res?.key?.id || null };
  });
  s.sendChain = job.catch(() => {});
  return job;
}

/**
 * Typed send (chat). Same per-session chain as `send`. A jid recipient is used as-is (existing conversation, no extra round-trip);
 * a phone number goes through onWhatsApp. Returns {message_id, to_jid, timestamp}.
 */
function sendTyped(id, b) {
  const s = get(id);
  const to = outbound.parseRecipient(b.to);
  const job = s.sendChain.then(async () => {
    if (s.status !== 'connected' || !s.sock) throw Object.assign(new Error('session not connected'), { code: 409 });
    let jid = to.jid;
    if (!jid) {
      const [exists] = await s.sock.onWhatsApp(`${to.digits}@s.whatsapp.net`);
      if (!exists?.exists) throw Object.assign(new Error('number is not on WhatsApp'), { code: 422 });
      jid = exists.jid;
    }
    const { content, opts, upload } = outbound.build(b, jid, { session: id });
    if (b.humanize === true) {
      await s.sock.sendPresenceUpdate('composing', jid);
      await sleep(Math.min(3000, 500 + String(content.text || '').length * 20));
      await s.sock.sendPresenceUpdate('paused', jid);
    }
    const res = await s.sock.sendMessage(jid, content, opts);
    if (upload) media.drop(upload);
    return { message_id: res?.key?.id || null, to_jid: jid, timestamp: Number(res?.messageTimestamp?.toNumber?.() ?? res?.messageTimestamp) || Math.floor(Date.now() / 1000) };
  });
  s.sendChain = job.catch(() => {});
  return job;
}

function live(id) {
  const s = get(id);
  if (s.status !== 'connected' || !s.sock) throw Object.assign(new Error('session not connected'), { code: 409 });
  return s.sock;
}

/** Send read receipts for the given incoming message ids of one chat. keys: [{id, participant?}] */
async function markRead(id, jid, keys) {
  if (!outbound.JID_RE.test(jid) || !Array.isArray(keys) || !keys.length || keys.length > 200) throw Object.assign(new Error('invalid payload'), { code: 422 });
  const list = keys.map((k) => {
    if (!k || !/^[A-Za-z0-9_-]{6,64}$/.test(k.id || '')) throw Object.assign(new Error('invalid payload'), { code: 422 });
    return { remoteJid: jid, id: k.id, fromMe: false, ...(k.participant && outbound.JID_RE.test(k.participant) ? { participant: k.participant } : {}) };
  });
  await live(id).readMessages(list);
  return { ok: true };
}

const PRESENCE = new Set(['composing', 'recording', 'paused', 'available', 'unavailable']);
async function setPresence(id, jid, state) {
  if (!outbound.JID_RE.test(jid) || !PRESENCE.has(state)) throw Object.assign(new Error('invalid payload'), { code: 422 });
  const sock = live(id);
  if (state === 'composing' || state === 'recording' || state === 'paused') await sock.sendPresenceUpdate(state, jid);
  else await sock.sendPresenceUpdate(state);
  return { ok: true };
}

/** Restore every session that has persisted credentials (service restart: no new QR scan). */
async function restoreAll() {
  fs.mkdirSync(cfg.authRoot, { recursive: true, mode: 0o700 });
  for (const id of fs.readdirSync(cfg.authRoot)) {
    if (ID_RE.test(id) && fs.existsSync(path.join(cfg.authRoot, id, 'creds.enc'))) {
      log.info({ id }, 'restoring session');
      await start(id).catch((e) => log.error({ id, err: e.message }, 'restore failed'));
    }
  }
}

async function shutdown() {
  for (const s of sessions.values()) {
    s.wantRun = false;
    clearTimeout(s.timer);
    try {
      s.sock?.end(undefined);
    } catch {}
  }
}

module.exports = { get, start, stop, send, sendTyped, markRead, setPresence, restoreAll, shutdown, ID_RE, sessions };
