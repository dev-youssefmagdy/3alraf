require('./helpers');
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const { seal, open, encryptedAuthState } = require('../src/crypto-store');
const { server } = require('../src/server');
const sm = require('../src/session-manager');
const { ID_RE, LEGACY_ID_RE, SESSION_ID } = sm;

const key = Buffer.from(process.env.WA_ENCRYPTION_KEY, 'base64');

test('session ids: only the single shop session `central` is accepted', () => {
  assert.strictEqual(SESSION_ID, 'central');
  assert.ok(ID_RE.test('central'));
  for (const bad of ['tenant-1', 'tenant-42', '../etc', 'tenant-', 'tenant-0', 'tenant-1/../x', 'Central', '', 'central/../x', 'tenant-1;rm']) assert.ok(!ID_RE.test(bad), bad);
  for (const legacy of ['tenant-1', 'tenant-42']) assert.ok(LEGACY_ID_RE.test(legacy), legacy);
  for (const notLegacy of ['tenant-0', 'tenant-', 'tenant-1/../x', 'central']) assert.ok(!LEGACY_ID_RE.test(notLegacy), notLegacy);
  assert.throws(() => sm.get('tenant-1'), (e) => e.code === 410);
  assert.throws(() => sm.get('../etc'), (e) => e.code === 400);
});

test('restoreAll skips (and keeps) retired tenant-N auth directories', async () => {
  const legacy = path.join(process.env.WA_AUTH_DIR, 'tenant-9');
  await (await encryptedAuthState(legacy, key)).saveCreds();
  await sm.restoreAll(); // no `central` creds yet -> nothing is started
  assert.ok(!sm.sessions.has('tenant-9'));
  assert.ok(fs.existsSync(path.join(legacy, 'creds.enc')), 'legacy dir must not be deleted');
});

test('credentials are encrypted at rest and tamper-evident', async () => {
  const dir = path.join(process.env.WA_AUTH_DIR, 'store-crypto-test');
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

test('the session is stored in its own directory, apart from a retired legacy one', async () => {
  await (await encryptedAuthState(path.join(process.env.WA_AUTH_DIR, 'central'), key)).saveCreds();
  await (await encryptedAuthState(path.join(process.env.WA_AUTH_DIR, 'tenant-8'), key)).saveCreds();
  const c = fs.readFileSync(path.join(process.env.WA_AUTH_DIR, 'central', 'creds.enc'));
  const t = fs.readFileSync(path.join(process.env.WA_AUTH_DIR, 'tenant-8', 'creds.enc'));
  assert.notDeepStrictEqual(c, t);
  fs.rmSync(path.join(process.env.WA_AUTH_DIR, 'central'), { recursive: true }); // keep later tests from restoring a fake session
});

test('HTTP API requires the bearer token and rejects bad ids', async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  assert.strictEqual((await fetch(`${base}/sessions/central`)).status, 401);
  assert.strictEqual((await fetch(`${base}/sessions/central`, { headers: { authorization: 'Bearer wrong' } })).status, 401);
  const h = { authorization: 'Bearer test-token' };
  assert.strictEqual((await fetch(`${base}/sessions/..%2Fetc`, { headers: h })).status, 400);
  const ok = await fetch(`${base}/sessions/central`, { headers: h });
  assert.strictEqual(ok.status, 200);
  assert.strictEqual((await ok.json()).status, 'disconnected');
  const send = await fetch(`${base}/sessions/central/messages`, { method: 'POST', headers: h, body: JSON.stringify({ to: '201001234567', text: 'hi' }) });
  assert.strictEqual(send.status, 409); // not connected
  const legacy = await fetch(`${base}/sessions/tenant-3`, { headers: h });
  assert.strictEqual(legacy.status, 410); // retired multi-store id: clear answer, nothing created
  assert.match((await legacy.json()).error, /central/);
  assert.strictEqual((await fetch(`${base}/sessions/tenant-3/messages`, { method: 'POST', headers: h, body: JSON.stringify({ to: '201001234567', text: 'hi' }) })).status, 410);
  assert.ok(!sm.sessions.has('tenant-3'));
  assert.strictEqual((await fetch(`${base}/health`)).status, 200);
  server.close();
});
