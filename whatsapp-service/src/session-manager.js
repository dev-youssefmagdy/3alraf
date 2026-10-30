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

async function send(id, to, text) {
  const s = get(id);
  if (s.status !== 'connected' || !s.sock) throw Object.assign(new Error('session not connected'), { code: 409 });
  const digits = String(to).replace(/\D/g, '');
  if (digits.length < 8 || digits.length > 15) throw Object.assign(new Error('invalid recipient'), { code: 422 });
  const jid = `${digits}@s.whatsapp.net`;
  const [exists] = await s.sock.onWhatsApp(jid);
  if (!exists?.exists) throw Object.assign(new Error('number is not on WhatsApp'), { code: 422 });
  const res = await s.sock.sendMessage(exists.jid, { text });
  return { message_id: res?.key?.id || null };
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

module.exports = { get, start, stop, send, restoreAll, shutdown, ID_RE, sessions };
