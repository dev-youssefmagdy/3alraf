require('./helpers');
const test = require('node:test');
const assert = require('node:assert');
const { EventEmitter } = require('events');
const { normalize } = require('../src/normalize');
const { build, parseRecipient } = require('../src/outbound');
const inbound = require('../src/inbound');
const media = require('../src/media');
const { server } = require('../src/server');

const key = (o = {}) => ({ id: 'ABCDEF123456', remoteJid: '201001234567@s.whatsapp.net', fromMe: false, ...o });
const wrap = (message, o = {}) => ({ key: key(o.key), messageTimestamp: 1700000000, pushName: 'Ali', message });

test('inbound: text, extended text with link and quote', () => {
  let n = normalize(wrap({ conversation: 'hello' }));
  assert.deepStrictEqual([n.kind, n.data.type, n.data.body, n.data.phone, n.data.from_me], ['message.in', 'text', 'hello', '201001234567', false]);
  n = normalize(wrap({ extendedTextMessage: { text: 'see x', matchedText: 'https://x.io', title: 'X', contextInfo: { stanzaId: 'Q1', participant: '2010@s.whatsapp.net' } } }));
  assert.strictEqual(n.data.payload.link.url, 'https://x.io');
  assert.deepStrictEqual(n.data.quoted, { id: 'Q1', participant: '2010@s.whatsapp.net' });
});

test('inbound: media types', () => {
  const img = normalize(wrap({ imageMessage: { mimetype: 'image/jpeg', fileLength: 10, caption: 'c', width: 5, height: 6, jpegThumbnail: Buffer.from('t') } })).data;
  assert.deepStrictEqual([img.type, img.caption, img.media.mime, img.media.size, img.media.thumbnail_b64], ['image', 'c', 'image/jpeg', 10, Buffer.from('t').toString('base64')]);
  assert.strictEqual(normalize(wrap({ audioMessage: { ptt: true, mimetype: 'audio/ogg', seconds: 3 } })).data.type, 'ptt');
  assert.strictEqual(normalize(wrap({ audioMessage: { mimetype: 'audio/mpeg' } })).data.type, 'audio');
  assert.strictEqual(normalize(wrap({ videoMessage: { mimetype: 'video/mp4' } })).data.type, 'video');
  assert.strictEqual(normalize(wrap({ documentMessage: { mimetype: 'application/pdf', fileName: 'a.pdf' } })).data.media.file_name, 'a.pdf');
  assert.strictEqual(normalize(wrap({ stickerMessage: { mimetype: 'image/webp', isAnimated: true } })).data.media.animated, true);
  assert.strictEqual(normalize(wrap({ ephemeralMessage: { message: { conversation: 'eph' } } })).data.body, 'eph'); // wrappers unwrapped
});

test('inbound: location, contact, poll, reaction, edit, delete, unsupported', () => {
  assert.deepStrictEqual(normalize(wrap({ locationMessage: { degreesLatitude: 1.5, degreesLongitude: 2.5, name: 'P' } })).data.payload, { lat: 1.5, lng: 2.5, name: 'P', address: null });
  assert.strictEqual(normalize(wrap({ liveLocationMessage: { degreesLatitude: 1, degreesLongitude: 2 } })).data.type, 'live_location');
  assert.strictEqual(normalize(wrap({ contactMessage: { displayName: 'Bob', vcard: 'BEGIN:VCARD' } })).data.payload.contacts[0].name, 'Bob');
  assert.strictEqual(normalize(wrap({ contactsArrayMessage: { contacts: [{ displayName: 'a' }, { displayName: 'b' }] } })).data.payload.contacts.length, 2);
  const poll = normalize(wrap({ pollCreationMessageV3: { name: 'Q?', options: [{ optionName: 'a' }, { optionName: 'b' }], selectableOptionsCount: 1 } })).data;
  assert.deepStrictEqual([poll.type, poll.body, poll.payload.options], ['poll', 'Q?', ['a', 'b']]);
  const r = normalize(wrap({ reactionMessage: { key: { id: 'T1', fromMe: true }, text: '👍' } }));
  assert.deepStrictEqual([r.kind, r.data.target_id, r.data.emoji, r.data.target_from_me], ['message.reaction', 'T1', '👍', true]);
  const d = normalize(wrap({ protocolMessage: { type: 0, key: { id: 'T2' } } }));
  assert.deepStrictEqual([d.kind, d.data.target_id], ['message.delete', 'T2']);
  const e = normalize(wrap({ protocolMessage: { type: 14, key: { id: 'T3' }, editedMessage: { conversation: 'new' } } }));
  assert.deepStrictEqual([e.kind, e.data.target_id, e.data.body], ['message.edit', 'T3', 'new']);
  const u = normalize(wrap({ somethingNewMessage: { x: 1 } }));
  assert.strictEqual(u.data.type, 'unsupported');
  assert.strictEqual(normalize({ key: key(), message: null }).data.type, 'unsupported');
});

test('inbound: groups, status and garbage are ignored without throwing', () => {
  assert.strictEqual(normalize(wrap({ conversation: 'x' }, { key: { remoteJid: '1-2@g.us' } })), null);
  assert.strictEqual(normalize(wrap({ conversation: 'x' }, { key: { remoteJid: 'status@broadcast' } })), null);
  assert.strictEqual(normalize(null), null);
  assert.strictEqual(normalize({}), null);
});

test('bind: emits envelopes, statuses, presence; downloads media to spool', async () => {
  const sock = new EventEmitter();
  sock.ev = sock;
  const out = [];
  inbound.bind(sock, 'central', { emit: (s, t, d) => out.push([s, t, d]), download: async () => Buffer.from('filebytes') });
  sock.emit('messages.upsert', { type: 'append', messages: [wrap({ conversation: 'ignored' })] });
  assert.strictEqual(out.length, 0);
  sock.emit('messages.upsert', { type: 'notify', messages: [wrap({ imageMessage: { mimetype: 'image/png', fileLength: 9 } }), wrap({ conversation: 'hi' }, { key: { id: 'ZZZZZZ1' } })] });
  await new Promise((r) => setTimeout(r, 20));
  assert.deepStrictEqual(out.map((o) => o[1]), ['message.in', 'message.in', 'media.ready']);
  const ready = out[2][2];
  assert.strictEqual(ready.status, 'ready');
  assert.strictEqual(ready.message_id, 'ABCDEF123456');
  assert.ok(media.lookup('central', ready.token));
  assert.strictEqual(media.lookup('tenant-1', ready.token), null); // other session can't read it
  out.length = 0;
  sock.emit('messages.update', [{ key: key({ fromMe: true }), update: { status: 4 } }, { key: key({ fromMe: false }), update: { status: 4 } }]);
  sock.emit('message-receipt.update', [{ key: key({ fromMe: true }), receipt: { userJid: 'x', receiptTimestamp: 1 } }]);
  sock.emit('presence.update', { id: '201001234567@s.whatsapp.net', presences: { '201001234567@s.whatsapp.net': { lastKnownPresence: 'composing' } } });
  sock.emit('contacts.upsert', [{ id: '201001234567@s.whatsapp.net', notify: 'Ali' }]);
  assert.deepStrictEqual(out.map((o) => o[1]), ['message.status', 'message.status', 'presence', 'contact.update']);
  assert.strictEqual(out[0][2].status, 'read');
  assert.strictEqual(out[1][2].status, 'delivered');
  assert.strictEqual(out[2][2].state, 'composing');
});

test('bind: oversize / failed download reports media.ready failed', async () => {
  const sock = new EventEmitter();
  sock.ev = sock;
  const out = [];
  inbound.bind(sock, 'central', { emit: (s, t, d) => out.push([t, d]), download: async () => { throw new Error('boom'); } });
  sock.emit('messages.upsert', { type: 'notify', messages: [wrap({ documentMessage: { mimetype: 'application/pdf', fileLength: 1e12 } })] });
  await new Promise((r) => setTimeout(r, 20));
  assert.deepStrictEqual([out[1][0], out[1][1].status, out[1][1].reason], ['media.ready', 'failed', 'too_large']);
});

const J = '201001234567@s.whatsapp.net';
test('outbound: text, legacy and reply/quote', () => {
  assert.deepStrictEqual(build({ to: '1', text: 'hi' }, J).content, { text: 'hi' });
  const r = build({ type: 'reply', reply_to: { id: 'MSG123456', from_me: false, body: 'orig' }, text: 'yo' }, J);
  assert.deepStrictEqual(r.content, { text: 'yo' });
  assert.deepStrictEqual(r.opts.quoted.key, { remoteJid: J, id: 'MSG123456', fromMe: false });
  assert.throws(() => build({ type: 'reply', text: 'x' }, J), /reply_to/);
  assert.throws(() => build({ type: 'text', text: '' }, J));
  assert.throws(() => build({ type: 'bogus' }, J));
});

test('outbound: media types, caps and mime checks', () => {
  const up = (mime, data = 'abc') => media.putBuffer('central', Buffer.from(data), { mime, name: 'f.bin' });
  const b = (type, mime, extra = {}) => build({ type, media: { upload_id: up(mime), file_name: '../a/b.pdf' }, ...extra }, J, { session: 'central' });
  assert.strictEqual(b('image', 'image/png', { caption: 'c' }).content.caption, 'c');
  assert.ok(b('video', 'video/mp4').content.video);
  assert.strictEqual(b('ptt', 'audio/ogg').content.mimetype, 'audio/ogg; codecs=opus');
  assert.strictEqual(b('ptt', 'audio/ogg').content.ptt, true);
  assert.strictEqual(b('audio', 'audio/mpeg').content.ptt, false);
  assert.ok(b('sticker', 'image/webp').content.sticker);
  const d = b('document', 'application/pdf');
  assert.strictEqual(d.content.fileName, '.._a_b.pdf');
  assert.ok(d.upload);
  assert.throws(() => b('image', 'application/pdf'), /mime/);
  assert.throws(() => b('sticker', 'image/png'), /mime/);
  assert.throws(() => build({ type: 'image', media: { upload_id: 'f'.repeat(32) } }, J, { session: 'central' }), /upload/);
  assert.throws(() => build({ type: 'image', media: { upload_id: up('image/png') } }, J, { session: 'tenant-1' }), /upload/); // another session's upload
});

test('outbound: location, contact, poll, reaction, edit, delete', () => {
  assert.deepStrictEqual(build({ type: 'location', location: { lat: 30, lng: 31, name: 'Cairo' } }, J).content.location, { degreesLatitude: 30, degreesLongitude: 31, name: 'Cairo' });
  assert.throws(() => build({ type: 'location', location: { lat: 99, lng: 0 } }, J));
  const c = build({ type: 'contact', contacts: [{ name: 'Bob', phone: '+20 100 123' }] }, J).content.contacts;
  assert.match(c.contacts[0].vcard, /waid=20100123/);
  assert.throws(() => build({ type: 'contact', contacts: [{ name: 'Bob' }] }, J));
  assert.deepStrictEqual(build({ type: 'poll', poll: { name: 'Q', options: ['a', 'b'] } }, J).content.poll, { name: 'Q', values: ['a', 'b'], selectableCount: 1 });
  assert.throws(() => build({ type: 'poll', poll: { name: 'Q', options: ['a', 'a'] } }, J));
  assert.throws(() => build({ type: 'poll', poll: { name: 'Q', options: ['a'] } }, J));
  assert.deepStrictEqual(build({ type: 'reaction', reaction: { message_id: 'MSG123456', emoji: '👍' } }, J).content.react, { text: '👍', key: { remoteJid: J, id: 'MSG123456', fromMe: false } });
  assert.deepStrictEqual(build({ type: 'edit', edit: { message_id: 'MSG123456', text: 'n' } }, J).content, { text: 'n', edit: { remoteJid: J, id: 'MSG123456', fromMe: true } });
  assert.deepStrictEqual(build({ type: 'delete', delete: { message_id: 'MSG123456', from_me: true } }, J).content, { delete: { remoteJid: J, id: 'MSG123456', fromMe: true } });
  assert.throws(() => build({ type: 'delete', delete: { message_id: 'x y' } }, J));
});

test('outbound: recipient validation', () => {
  assert.deepStrictEqual(parseRecipient('+20 100 1234567'), { digits: '201001234567' });
  assert.deepStrictEqual(parseRecipient(J), { jid: J });
  for (const bad of ['123', 'abc@s.whatsapp.net', '1-2@g.us', 5, null]) assert.throws(() => parseRecipient(bad), `${bad}`);
});

test('HTTP: typed send validation, uploads, media pull, session isolation', async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  const h = { authorization: 'Bearer test-token' };
  const post = (p, body, headers = {}) => fetch(base + p, { method: 'POST', headers: { ...h, ...headers }, body });
  // bad typed payload is rejected before touching the socket
  assert.strictEqual((await post('/sessions/central/messages', JSON.stringify({ to: 'nope', type: 'text', text: 'x' }))).status, 422);
  assert.strictEqual((await post('/sessions/central/messages', JSON.stringify({ to: J, type: 'text', text: 'x' }))).status, 409);
  assert.strictEqual((await post('/sessions/central/read', JSON.stringify({ jid: 'bad', keys: [] }))).status, 422);
  assert.strictEqual((await post('/sessions/central/presence', JSON.stringify({ jid: J, state: 'composing' }))).status, 409);
  // upload + pull
  const up = await post('/sessions/central/uploads?name=a.png', Buffer.from('PNGDATA'), { 'content-type': 'image/png' });
  assert.strictEqual(up.status, 201);
  const { upload_id } = await up.json();
  assert.strictEqual((await fetch(`${base}/sessions/tenant-1/media/${upload_id}`, { headers: h })).status, 404);
  const got = await fetch(`${base}/sessions/central/media/${upload_id}`, { headers: h });
  assert.strictEqual(got.status, 200);
  assert.strictEqual(got.headers.get('content-type'), 'image/png');
  assert.strictEqual(await got.text(), 'PNGDATA');
  assert.strictEqual((await fetch(`${base}/sessions/central/media/${upload_id}`, { method: 'DELETE', headers: h })).status, 200);
  assert.strictEqual((await fetch(`${base}/sessions/central/media/${upload_id}`, { headers: h })).status, 404);
  assert.strictEqual((await post('/sessions/central/uploads', Buffer.from(''), { 'content-type': 'image/png' })).status, 422);
  assert.strictEqual((await fetch(`${base}/sessions/central/media/${upload_id}`)).status, 401);
  server.closeAllConnections();
  server.close();
});
