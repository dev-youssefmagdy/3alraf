'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { proto, initAuthCreds, BufferJSON } = require('@whiskeysockets/baileys');

/** AES-256-GCM file store: every Baileys auth file is encrypted at rest. */
function seal(key, plain) {
  const iv = crypto.randomBytes(12);
  const c = crypto.createCipheriv('aes-256-gcm', key, iv);
  const enc = Buffer.concat([c.update(plain, 'utf8'), c.final()]);
  return Buffer.concat([iv, c.getAuthTag(), enc]);
}
function open(key, buf) {
  const d = crypto.createDecipheriv('aes-256-gcm', key, buf.subarray(0, 12));
  d.setAuthTag(buf.subarray(12, 28));
  return Buffer.concat([d.update(buf.subarray(28)), d.final()]).toString('utf8');
}

const safe = (n) => n.replace(/\//g, '__').replace(/:/g, '-');

async function encryptedAuthState(dir, key) {
  fs.mkdirSync(dir, { recursive: true, mode: 0o700 });
  const file = (n) => path.join(dir, safe(n) + '.enc');
  const write = (n, data) => {
    const tmp = file(n) + '.tmp';
    fs.writeFileSync(tmp, seal(key, JSON.stringify(data, BufferJSON.replacer)), { mode: 0o600 });
    fs.renameSync(tmp, file(n)); // atomic: a crash never leaves a half-written credential
  };
  const read = (n) => {
    try {
      return JSON.parse(open(key, fs.readFileSync(file(n))), BufferJSON.reviver);
    } catch {
      return null;
    }
  };
  const del = (n) => fs.rmSync(file(n), { force: true });

  const creds = read('creds') || initAuthCreds();
  return {
    state: {
      creds,
      keys: {
        get: async (type, ids) => {
          const out = {};
          for (const id of ids) {
            let v = read(`${type}-${id}`);
            if (type === 'app-state-sync-key' && v) v = proto.Message.AppStateSyncKeyData.fromObject(v);
            out[id] = v;
          }
          return out;
        },
        set: async (data) => {
          for (const type of Object.keys(data))
            for (const id of Object.keys(data[type])) {
              const v = data[type][id];
              v ? write(`${type}-${id}`, v) : del(`${type}-${id}`);
            }
        },
      },
    },
    saveCreds: async () => write('creds', creds),
    hasCreds: () => fs.existsSync(file('creds')),
  };
}

module.exports = { encryptedAuthState, seal, open };
