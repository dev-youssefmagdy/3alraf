require('./helpers');
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const { seal, open, encryptedAuthState } = require('../src/crypto-store');
const { server } = require('../src/server');
const { ID_RE } = require('../src/session-manager');

const key = Buffer.from(process.env.WA_ENCRYPTION_KEY, 'base64');

test('session ids: only central and tenant-N are accepted', () => {
  for (const ok of ['central', 'tenant-1', 'tenant-42']) assert.ok(ID_RE.test(ok), ok);
  for (const bad of ['../etc', 'tenant-', 'tenant-0', 'tenant-1/../x', 'Central', '', 'tenant-1;rm']) assert.ok(!ID_RE.test(bad), bad);
});

test('credentials are encrypted at rest and tamper-evident', async () => {
  const dir = path.join(process.env.WA_AUTH_DIR, 'tenant-7');
  const a = await encryptedAuthState(dir, key);
  await a.saveCreds();
  const raw = fs.readFileSync(path.join(dir, 'creds.enc'));
  assert.ok(!raw.toString('utf8').includes('noiseKey'), 'plaintext leaked');
  assert.ok(a.hasCreds());
  const b = await encryptedAuthState(dir, key); // reload = restart
  assert.deepStrictEqual(Buffer.from(b.state.creds.noiseKey.private), Buffer.from(a.state.creds.noiseKey.private));
  raw[raw.length - 1] ^= 1;
  assert.throws(() => open(key, raw));
  assert.strictEqual(open(key, seal(key, 'x')), 'x');
});

test('sessions are stored in separate directories', async () => {
  await (await encryptedAuthState(path.join(process.env.WA_AUTH_DIR, 'central'), key)).saveCreds();
  await (await encryptedAuthState(path.join(process.env.WA_AUTH_DIR, 'tenant-8'), key)).saveCreds();
  const c = fs.readFileSync(path.join(process.env.WA_AUTH_DIR, 'central', 'creds.enc'));
  const t = fs.readFileSync(path.join(process.env.WA_AUTH_DIR, 'tenant-8', 'creds.enc'));
  assert.notDeepStrictEqual(c, t);
});

test('HTTP API requires the bearer token and rejects bad ids', async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  assert.strictEqual((await fetch(`${base}/sessions/central`)).status, 401);
  assert.strictEqual((await fetch(`${base}/sessions/central`, { headers: { authorization: 'Bearer wrong' } })).status, 401);
  const h = { authorization: 'Bearer test-token' };
  assert.strictEqual((await fetch(`${base}/sessions/..%2Fetc`, { headers: h })).status, 400);
  const ok = await fetch(`${base}/sessions/tenant-3`, { headers: h });
  assert.strictEqual(ok.status, 200);
  assert.strictEqual((await ok.json()).status, 'disconnected');
  const send = await fetch(`${base}/sessions/tenant-3/messages`, { method: 'POST', headers: h, body: JSON.stringify({ to: '201001234567', text: 'hi' }) });
  assert.strictEqual(send.status, 409); // not connected: never falls back to another session
  assert.strictEqual((await fetch(`${base}/health`)).status, 200);
  server.close();
});
