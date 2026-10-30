'use strict';
const fs = require('fs');
const cfg = require('./config');
const media = require('./media');

const bad = (msg, code = 422) => Object.assign(new Error(msg), { code });
const TYPES = new Set(['text', 'image', 'video', 'audio', 'ptt', 'document', 'sticker', 'location', 'contact', 'poll', 'reaction', 'reply', 'edit', 'delete']);
const MEDIA_MIME = { image: /^image\/(jpeg|png|webp|gif)$/, video: /^video\/(mp4|3gpp|quicktime|webm)$/, audio: /^audio\//, ptt: /^audio\//, sticker: /^image\/webp$/, document: /^[\w.+-]+\/[\w.+-]+$/ };
const JID_RE = /^(\d{5,20}@s\.whatsapp\.net|\d{5,25}@lid)$/;
const MSG_ID_RE = /^[A-Za-z0-9_-]{6,64}$/;
const str = (v, max, name) => {
  if (typeof v !== 'string' || !v.length || v.length > max) throw bad(`invalid ${name}`);
  return v;
};

/** Recipient: a full user jid (existing conversation: used as-is) or a phone number (needs the onWhatsApp lookup). */
function parseRecipient(to) {
  if (typeof to !== 'string') throw bad('invalid recipient');
  if (to.includes('@')) {
    if (!JID_RE.test(to)) throw bad('invalid recipient');
    return { jid: to };
  }
  const digits = to.replace(/\D/g, '');
  if (digits.length < 8 || digits.length > 15) throw bad('invalid recipient');
  return { digits };
}

function ref(r, chatJid, name) {
  if (!r || typeof r !== 'object' || !MSG_ID_RE.test(r.id || r.message_id || '')) throw bad(`invalid ${name}`);
  const participant = r.participant && JID_RE.test(r.participant) ? r.participant : undefined;
  return { remoteJid: chatJid, id: r.id || r.message_id, fromMe: r.from_me === true, ...(participant ? { participant } : {}) };
}

function vcard(c) {
  const name = str(c?.name, 100, 'contact name').replace(/[\r\n;]/g, ' ');
  if (typeof c.vcard === 'string' && c.vcard.startsWith('BEGIN:VCARD') && c.vcard.length <= 8192) return { displayName: name, vcard: c.vcard };
  const phone = String(c.phone || '').replace(/\D/g, '');
  if (phone.length < 5 || phone.length > 15) throw bad('invalid contact phone');
  return { displayName: name, vcard: `BEGIN:VCARD\nVERSION:3.0\nFN:${name}\nTEL;type=CELL;waid=${phone}:+${phone}\nEND:VCARD` };
}

/**
 * Typed payload -> Baileys content (+ optional send options). Pure except for reading a previously uploaded spool file.
 * Payload: {to, type, text?, caption?, media?:{upload_id, file_name?}, location?:{lat,lng,name?,address?}, contacts?:[{name, phone|vcard}],
 *           poll?:{name, options[], selectable_count?}, reaction?:{message_id, from_me?, participant?, emoji}, reply_to?:{id, from_me?, participant?, body?},
 *           edit?:{message_id, text}, delete?:{message_id}}.
 * Legacy {to, text} (no type) == type text. `chatJid` is the resolved recipient jid, used to build keys.
 */
function build(b, chatJid, { session } = {}) {
  const type = b.type === undefined ? 'text' : b.type;
  if (!TYPES.has(type)) throw bad('unsupported type');
  const opts = {};
  let content;
  let effective = type;
  let upload = null;

  if (b.reply_to) {
    const k = ref(b.reply_to, chatJid, 'reply_to');
    opts.quoted = { key: k, message: { conversation: typeof b.reply_to.body === 'string' ? b.reply_to.body.slice(0, 1024) : '' } };
  }
  if (type === 'reply') {
    if (!b.reply_to) throw bad('reply_to required');
    effective = b.media ? b.media_type : 'text';
    if (!effective || (b.media && !MEDIA_MIME[effective])) throw bad('invalid reply content');
  }

  switch (effective) {
    case 'text':
      content = { text: str(b.text, 4096, 'text') };
      break;
    case 'image':
    case 'video':
    case 'audio':
    case 'ptt':
    case 'document':
    case 'sticker': {
      const m = b.media;
      if (!m || typeof m !== 'object') throw bad('media required');
      const e = media.lookup(session, m.upload_id);
      if (!e) throw bad('upload not found or expired', 404);
      if (!MEDIA_MIME[effective].test(e.mime)) throw bad('mime not allowed for this type');
      if (e.size > cfg.caps[effective]) throw bad('file too large', 413);
      const buf = fs.readFileSync(e.file);
      const cap = b.caption === undefined || b.caption === null ? undefined : str(b.caption, 1024, 'caption');
      if (effective === 'image') content = { image: buf, mimetype: e.mime, ...(cap ? { caption: cap } : {}) };
      else if (effective === 'video') content = { video: buf, mimetype: e.mime, ...(cap ? { caption: cap } : {}), ...(m.gif === true ? { gifPlayback: true } : {}) };
      else if (effective === 'audio') content = { audio: buf, mimetype: e.mime, ptt: false };
      else if (effective === 'ptt') content = { audio: buf, mimetype: /ogg/.test(e.mime) ? 'audio/ogg; codecs=opus' : e.mime, ptt: true };
      else if (effective === 'sticker') content = { sticker: buf };
      else content = { document: buf, mimetype: e.mime, fileName: String(m.file_name || e.name || 'file').replace(/[\\/\r\n\0]/g, '_').slice(0, 200), ...(cap ? { caption: cap } : {}) };
      upload = m.upload_id; // caller frees the spool entry after a successful send
      break;
    }
    case 'location': {
      const l = b.location;
      if (!l || !Number.isFinite(l.lat) || !Number.isFinite(l.lng) || Math.abs(l.lat) > 90 || Math.abs(l.lng) > 180) throw bad('invalid location');
      content = { location: { degreesLatitude: l.lat, degreesLongitude: l.lng, ...(l.name ? { name: String(l.name).slice(0, 200) } : {}), ...(l.address ? { address: String(l.address).slice(0, 300) } : {}) } };
      break;
    }
    case 'contact': {
      if (!Array.isArray(b.contacts) || b.contacts.length < 1 || b.contacts.length > 10) throw bad('invalid contacts');
      const contacts = b.contacts.map(vcard);
      content = { contacts: { displayName: contacts.length === 1 ? contacts[0].displayName : `${contacts.length} contacts`, contacts } };
      break;
    }
    case 'poll': {
      const p = b.poll;
      if (!p || !Array.isArray(p.options) || p.options.length < 2 || p.options.length > 12) throw bad('invalid poll');
      const values = p.options.map((o) => str(o, 100, 'poll option'));
      if (new Set(values).size !== values.length) throw bad('duplicate poll options');
      const sel = p.selectable_count === undefined ? 1 : p.selectable_count;
      if (!Number.isInteger(sel) || sel < 0 || sel > values.length) throw bad('invalid selectable_count');
      content = { poll: { name: str(p.name, 255, 'poll name'), values, selectableCount: sel } };
      break;
    }
    case 'reaction': {
      const r = b.reaction;
      if (!r || typeof r.emoji !== 'string' || r.emoji.length > 16) throw bad('invalid reaction'); // '' removes the reaction
      content = { react: { text: r.emoji, key: ref(r, chatJid, 'reaction target') } };
      break;
    }
    case 'edit': {
      const e = b.edit;
      if (!e) throw bad('edit required');
      content = { text: str(e.text, 4096, 'text'), edit: { ...ref({ ...e, from_me: true }, chatJid, 'edit target') } };
      break;
    }
    case 'delete': {
      if (!b.delete) throw bad('delete required');
      content = { delete: ref({ ...b.delete, from_me: b.delete.from_me === true }, chatJid, 'delete target') };
      break;
    }
  }
  return { content, opts, upload };
}

module.exports = { build, parseRecipient, TYPES, JID_RE };
