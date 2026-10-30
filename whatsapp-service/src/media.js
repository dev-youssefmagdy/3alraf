'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const cfg = require('./config');

/**
 * Transient media spool (private dir, 0700). Both directions use opaque random ids and a TTL:
 *  - inbound: Node downloads -> spool -> `media.ready` event -> Laravel PULLs GET /sessions/:id/media/:id (then DELETE).
 *  - outbound: Laravel PUSHes POST /sessions/:id/uploads (raw body) -> upload_id -> referenced by a typed send.
 * No remote URL is ever fetched by Node.
 */
const entries = new Map(); // id -> {session, file, mime, size, name, sha256, exp}
const ID_RE = /^[a-f0-9]{32}$/;

function ensureDir() {
  fs.mkdirSync(cfg.spoolDir, { recursive: true, mode: 0o700 });
}

function newId() {
  return crypto.randomBytes(16).toString('hex');
}

function putBuffer(session, buf, meta = {}) {
  ensureDir();
  const id = newId();
  const file = path.join(cfg.spoolDir, id);
  fs.writeFileSync(file, buf, { mode: 0o600 });
  entries.set(id, { session, file, mime: meta.mime || 'application/octet-stream', size: buf.length, name: meta.name || null, sha256: crypto.createHash('sha256').update(buf).digest('hex'), exp: Date.now() + cfg.mediaTtlMs });
  return id;
}

/** Stream a request body to the spool, enforcing the size cap while receiving. */
function putStream(session, req, meta, maxBytes = cfg.maxUploadBytes) {
  ensureDir();
  const id = newId();
  const file = path.join(cfg.spoolDir, id);
  return new Promise((resolve, reject) => {
    const out = fs.createWriteStream(file, { mode: 0o600 });
    const hash = crypto.createHash('sha256');
    let n = 0;
    const fail = (e) => {
      out.destroy();
      fs.rmSync(file, { force: true });
      reject(e);
    };
    req.on('data', (c) => {
      n += c.length;
      if (n > maxBytes) {
        req.destroy();
        return fail(Object.assign(new Error('file too large'), { code: 413 }));
      }
      hash.update(c);
      out.write(c);
    });
    req.on('error', fail);
    req.on('end', () =>
      out.end(() => {
        if (n === 0) return fail(Object.assign(new Error('empty body'), { code: 422 }));
        entries.set(id, { session, file, mime: meta.mime || 'application/octet-stream', size: n, name: meta.name || null, sha256: hash.digest('hex'), exp: Date.now() + cfg.mediaTtlMs });
        resolve(id);
      }),
    );
  });
}

/** Look up an entry; only the session that owns it may see it. */
function lookup(session, id) {
  const e = ID_RE.test(String(id)) ? entries.get(id) : null;
  if (!e || e.session !== session || e.exp < Date.now()) return null;
  return e;
}

function drop(id) {
  const e = entries.get(id);
  if (!e) return false;
  entries.delete(id);
  fs.rmSync(e.file, { force: true });
  return true;
}

function sweep() {
  const now = Date.now();
  for (const [id, e] of entries) if (e.exp < now) drop(id);
}
const timer = setInterval(sweep, 60_000);
timer.unref();

module.exports = { putBuffer, putStream, lookup, drop, sweep, ID_RE, entries };
