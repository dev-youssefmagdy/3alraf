'use strict';
const http = require('http');
const crypto = require('crypto');
const cfg = require('./config');
const sm = require('./session-manager');

function authorized(req) {
  const given = Buffer.from((req.headers.authorization || '').replace(/^Bearer /, ''));
  const want = Buffer.from(cfg.apiToken);
  return given.length === want.length && crypto.timingSafeEqual(given, want);
}

const json = (res, code, body) => {
  res.writeHead(code, { 'content-type': 'application/json' });
  res.end(JSON.stringify(body));
};

const readBody = (req) =>
  new Promise((resolve, reject) => {
    let n = 0;
    const chunks = [];
    req.on('data', (c) => {
      n += c.length;
      if (n > 64 * 1024) return reject(Object.assign(new Error('body too large'), { code: 413 }));
      chunks.push(c);
    });
    req.on('end', () => {
      try {
        resolve(chunks.length ? JSON.parse(Buffer.concat(chunks)) : {});
      } catch {
        reject(Object.assign(new Error('bad json'), { code: 400 }));
      }
    });
  });

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://x');
    if (url.pathname === '/health') return json(res, 200, { ok: true, sessions: sm.sessions.size });
    if (!authorized(req)) return json(res, 401, { error: 'unauthorized' });

    const m = url.pathname.match(/^\/sessions\/([^/]+)(?:\/(connect|reconnect|disconnect|logout|messages))?$/);
    if (!m) return json(res, 404, { error: 'not found' });
    const [, id, action] = m;
    const s = sm.get(id);

    if (req.method === 'GET' && !action) return json(res, 200, s.snapshot());
    if (req.method === 'POST' && (action === 'connect' || action === 'reconnect')) {
      if (action === 'reconnect') await sm.stop(id, { logout: false });
      await sm.start(id);
      return json(res, 202, s.snapshot());
    }
    if (req.method === 'POST' && action === 'disconnect') return json(res, 200, (await sm.stop(id, { logout: true })).snapshot());
    if (req.method === 'POST' && action === 'messages') {
      const b = await readBody(req);
      if (typeof b.to !== 'string' || typeof b.text !== 'string' || !b.text || b.text.length > 4096) return json(res, 422, { error: 'invalid payload' });
      const gap = (v) => Math.min(60_000, Math.max(0, Number.isFinite(b[v]) ? b[v] : 0));
      return json(res, 200, await sm.send(id, b.to, b.text, { humanize: b.humanize === true, minGapMs: gap('min_gap_ms'), maxGapMs: gap('max_gap_ms') }));
    }
    return json(res, 405, { error: 'method not allowed' });
  } catch (e) {
    return json(res, e.code && Number.isInteger(e.code) ? e.code : 500, { error: e.message });
  }
});

module.exports = { server };

if (require.main === module) {
  server.listen(cfg.port, cfg.host, () => console.log(`whatsapp-service on ${cfg.host}:${cfg.port}`));
  sm.restoreAll();
  const bye = async () => {
    await sm.shutdown();
    server.close(() => process.exit(0));
  };
  process.on('SIGTERM', bye);
  process.on('SIGINT', bye);
}
