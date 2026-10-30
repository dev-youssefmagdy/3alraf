'use strict';
const { normalizeMessageContent, getContentType } = require('@whiskeysockets/baileys');

const toNum = (v) => (v && typeof v === 'object' && typeof v.toNumber === 'function' ? v.toNumber() : Number(v) || 0);
const b64 = (u8) => (u8 && u8.length ? Buffer.from(u8).toString('base64') : null);

const MEDIA_KEYS = { imageMessage: 'image', videoMessage: 'video', audioMessage: 'audio', documentMessage: 'document', stickerMessage: 'sticker' };

/** Digits of a user jid, or null for lid/other namespaces. */
function phoneOf(jid) {
  const m = /^(\d{5,20})(?::\d+)?@s\.whatsapp\.net$/.exec(jid || '');
  return m ? m[1] : null;
}

function quotedOf(ctx) {
  if (!ctx?.stanzaId) return null;
  return { id: ctx.stanzaId, participant: ctx.participant || null };
}

/** Status ints from Baileys (WAMessageStatus) -> our states. */
const STATUS = { 0: 'failed', 1: 'pending', 2: 'sent', 3: 'delivered', 4: 'read', 5: 'read' };
const statusOf = (n) => STATUS[n] || null;

/**
 * Normalize a Baileys WAMessage into a stable envelope.
 * Returns {kind, data} where kind is one of message.in | message.reaction | message.edit | message.delete, or null when it must be ignored
 * (groups, broadcasts/status, protocol noise). Unknown content becomes type=unsupported (never throws).
 */
function normalize(msg) {
  const key = msg?.key;
  if (!key?.id || !key.remoteJid) return null;
  const jid = key.remoteJid;
  if (jid.endsWith('@g.us') || jid.endsWith('@broadcast') || jid.endsWith('@newsletter') || jid === 'status@broadcast') return null;

  const base = {
    id: key.id,
    jid,
    jid_alt: key.remoteJidAlt || null,
    phone: phoneOf(key.remoteJidAlt) || phoneOf(jid),
    from_me: !!key.fromMe,
    timestamp: toNum(msg.messageTimestamp) || Math.floor(Date.now() / 1000),
    push_name: msg.pushName || null,
  };

  let content;
  try {
    content = normalizeMessageContent(msg.message);
  } catch {
    content = null;
  }
  if (!content) return { kind: 'message.in', data: { ...base, type: 'unsupported', body: null, caption: null, payload: {}, quoted: null, media: null } };
  const ct = getContentType(content);
  const m = content[ct];
  const out = { ...base, body: null, caption: null, payload: {}, quoted: null, media: null };

  if (ct === 'protocolMessage') {
    const p = content.protocolMessage;
    if (p.type === 0 && p.key?.id) return { kind: 'message.delete', data: { ...base, target_id: p.key.id, target_from_me: !!p.key.fromMe } };
    if (p.type === 14 && p.key?.id && p.editedMessage) {
      const inner = normalizeMessageContent(p.editedMessage);
      const it = inner ? getContentType(inner) : null;
      const text = it === 'conversation' ? inner.conversation : it === 'extendedTextMessage' ? inner.extendedTextMessage.text : inner?.[it]?.caption || '';
      return { kind: 'message.edit', data: { ...base, target_id: p.key.id, target_from_me: !!p.key.fromMe, body: text || '', edited_at: toNum(p.timestampMs) ? Math.floor(toNum(p.timestampMs) / 1000) : base.timestamp } };
    }
    return null;
  }
  if (ct === 'senderKeyDistributionMessage' || ct === 'messageContextInfo') return null;

  if (ct === 'reactionMessage') {
    return { kind: 'message.reaction', data: { ...base, target_id: m.key?.id || null, target_from_me: !!m.key?.fromMe, emoji: m.text || '' } }; // empty emoji = reaction removed
  }

  switch (ct) {
    case 'conversation':
      return { kind: 'message.in', data: { ...out, type: 'text', body: m } };
    case 'extendedTextMessage': {
      const p = m.matchedText ? { link: { url: m.matchedText, title: m.title || null, description: m.description || null, thumbnail_b64: b64(m.jpegThumbnail) } } : {};
      return { kind: 'message.in', data: { ...out, type: 'text', body: m.text || '', payload: p, quoted: quotedOf(m.contextInfo) } };
    }
    case 'imageMessage':
    case 'videoMessage':
    case 'audioMessage':
    case 'documentMessage':
    case 'stickerMessage': {
      const kind = MEDIA_KEYS[ct];
      const type = kind === 'audio' && m.ptt ? 'ptt' : kind;
      return {
        kind: 'message.in',
        data: {
          ...out,
          type,
          caption: m.caption || null,
          quoted: quotedOf(m.contextInfo),
          media: {
            mime: m.mimetype || null,
            size: toNum(m.fileLength),
            file_name: m.fileName || null,
            width: m.width || null,
            height: m.height || null,
            duration: m.seconds || null,
            animated: !!(m.isAnimated || m.gifPlayback),
            thumbnail_b64: b64(m.jpegThumbnail),
          },
        },
      };
    }
    case 'locationMessage':
      return { kind: 'message.in', data: { ...out, type: 'location', quoted: quotedOf(m.contextInfo), payload: { lat: m.degreesLatitude, lng: m.degreesLongitude, name: m.name || null, address: m.address || null } } };
    case 'liveLocationMessage':
      return { kind: 'message.in', data: { ...out, type: 'live_location', caption: m.caption || null, quoted: quotedOf(m.contextInfo), payload: { lat: m.degreesLatitude, lng: m.degreesLongitude } } };
    case 'contactMessage':
      return { kind: 'message.in', data: { ...out, type: 'contact', quoted: quotedOf(m.contextInfo), payload: { contacts: [{ name: m.displayName || null, vcard: m.vcard || null }] } } };
    case 'contactsArrayMessage':
      return { kind: 'message.in', data: { ...out, type: 'contact', quoted: quotedOf(m.contextInfo), payload: { contacts: (m.contacts || []).map((c) => ({ name: c.displayName || null, vcard: c.vcard || null })) } } };
    case 'pollCreationMessage':
    case 'pollCreationMessageV2':
    case 'pollCreationMessageV3':
      return { kind: 'message.in', data: { ...out, type: 'poll', body: m.name || '', quoted: quotedOf(m.contextInfo), payload: { options: (m.options || []).map((o) => o.optionName), selectable_count: m.selectableOptionsCount || 0 } } };
    default:
      return { kind: 'message.in', data: { ...out, type: 'unsupported', payload: { wa_type: ct } } };
  }
}

module.exports = { normalize, statusOf, phoneOf };
