#!/usr/bin/env python3
"""Generates n8n/workflows/*.json (n8n 1.123.x import format). Edit here, run `python3 bin/build-workflows.py`, then re-import (README)."""
import json, os, uuid

OUT = '/var/www/3alraf/n8n/workflows'
TZ = 'Africa/Cairo'
ID = {
    'error': '3alrafErrorHnd00', 'weekly': '3alrafGiftWeek01', 'untagged': '3alrafUntagged02',
    'occasion': '3alrafOccasion03', 'health': '3alrafHealthMn04', 'notify': '3alrafNotifyOw90', 'api': '3alrafApiCallS91',
}
CRED = {'httpHeaderAuth': {'id': '3alrafIntegBearr', 'name': '3alraf integration (bearer)'}}
NS = uuid.UUID('6b1f3c1e-3a1a-4f00-9a00-3a1raf000000'.replace('r', '0'))


def nid(wf, name):
    return str(uuid.uuid5(NS, wf + '/' + name))


def node(wf, name, type_, ver, pos, params, **extra):
    n = {'parameters': params, 'id': nid(wf, name), 'name': name, 'type': type_, 'typeVersion': ver, 'position': pos}
    n.update(extra)
    return n


def code(wf, name, pos, js, **extra):
    return node(wf, name, 'n8n-nodes-base.code', 2, pos, {'jsCode': js.strip() + '\n'}, **extra)


def schedule(wf, name, pos, cron):
    return node(wf, name, 'n8n-nodes-base.scheduleTrigger', 1.2, pos, {'rule': {'interval': [{'field': 'cronExpression', 'expression': cron}]}})


def call(wf, name, pos, target, **extra):
    return node(wf, name, 'n8n-nodes-base.executeWorkflow', 1.2, pos, {
        'workflowId': {'__rl': True, 'mode': 'id', 'value': ID[target]},
        'workflowInputs': {'mappingMode': 'defineBelow', 'value': {}, 'matchingColumns': [], 'schema': [],
                           'attemptToConvertTypes': False, 'convertFieldsToString': True},
        'options': {'waitForSubWorkflow': True},
    }, **extra)


def iff(wf, name, pos, left, op_type='boolean', op='true'):
    return node(wf, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': nid(wf, name + '/c'), 'leftValue': left, 'rightValue': '',
                                       'operator': {'type': op_type, 'operation': op, 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


def manual(wf, pos=(0, 200)):
    # lets the owner (editor "Test workflow") and `n8n execute --id` run the same chain on demand
    return node(wf, 'Run manually', 'n8n-nodes-base.manualTrigger', 1, list(pos), {})


def noop(wf, name, pos):
    return node(wf, name, 'n8n-nodes-base.noOp', 1, pos, {})


def sub_trigger(wf, pos=(0, 0)):
    return node(wf, 'When called by another workflow', 'n8n-nodes-base.executeWorkflowTrigger', 1.1, list(pos), {'inputSource': 'passthrough'})


def link(pairs):
    """pairs: list of (from, to) or (from, to, output_index)"""
    c = {}
    for p in pairs:
        src, dst = p[0], p[1]
        out = p[2] if len(p) > 2 else 0
        main = c.setdefault(src, {'main': []})['main']
        while len(main) <= out:
            main.append([])
        main[out].append({'node': dst, 'type': 'main', 'index': 0})
    return c


def workflow(key, name, nodes, conns, error_wf=True, description=''):
    settings = {'executionOrder': 'v1', 'timezone': TZ, 'saveManualExecutions': True, 'saveDataErrorExecution': 'all',
                'saveDataSuccessExecution': 'all', 'saveExecutionProgress': False, 'callerPolicy': 'workflowsFromSameOwner'}
    if error_wf:
        settings['errorWorkflow'] = ID['error']
    return {'id': ID[key], 'name': name, 'active': False, 'isArchived': False, 'nodes': nodes, 'connections': conns,
            'settings': settings, 'pinData': {}, 'meta': {'templateCredsSetupCompleted': True},
            'tags': [], 'versionId': str(uuid.uuid5(NS, key + '-v1'))}


# ------------------------------------------------------------------------------------------------ shared JS helpers
HELPERS = r"""
const SITE = String($env.THREEALRAF_SITE_URL || '').replace(/\/+$/, '');
const OCC_AR = { birthday: 'عيد ميلاد', mothers_day: 'عيد الأم', valentine: 'عيد الحب', wedding: 'فرح', eid_fitr: 'عيد الفطر',
  eid_adha: 'عيد الأضحى', ramadan: 'رمضان', graduation: 'تخرج', thanks: 'شكر', self: 'لنفسي' };
const num = (v) => (v === null || v === undefined) ? '—' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 });
const pct = (v) => (v === null || v === undefined) ? '—' : num(v) + '%';
const money = (v) => (v === null || v === undefined) ? '—' : num(v) + ' ج.م';
const requireOk = (results) => {
  const bad = results.filter((r) => !r.ok);
  if (bad.length) throw new Error('3alraf integration API failed: ' + bad.map((r) => `${r.path} -> ${r.status} ${r.error}`).join('; '));
};
"""

# ------------------------------------------------------------------------------------------------ 91 API request
W = 'api'
SIGN = r"""
// Signs each request for the 3alraf integration API (backend/docs/api/integration.md):
//   X-Timestamp = unix seconds, X-Signature = hex HMAC-SHA256(secret, "{X-Timestamp}.{raw body}") — GET, so body = ''.
// The bearer token is NOT here: the HTTP node adds it from the encrypted credential "3alraf integration (bearer)".
// Input items: { path: 'gift-finder/stats', query: { days: 7 }, label?: 'week' }.
const crypto = require('crypto');
const base = String($env.THREEALRAF_API_BASE || '').replace(/\/+$/, '');
const secret = String($env.THREEALRAF_HMAC_SECRET || '');
if (!base || !secret) {
  throw new Error('THREEALRAF_API_BASE / THREEALRAF_HMAC_SECRET missing in n8n/.env (run n8n/provision-client.sh, then restart n8n)');
}
return $input.all().map((item) => {
  const path = String(item.json.path || 'ping').replace(/^\/+/, '');
  if (!/^[a-z0-9\-\/]+$/.test(path)) throw new Error('Invalid API path: ' + path);
  const query = item.json.query || {};
  const qs = Object.entries(query).map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(String(v))).join('&'); // no URLSearchParams in the runner sandbox
  const ts = Math.floor(Date.now() / 1000).toString();
  const signature = crypto.createHmac('sha256', secret).update(ts + '.' + '').digest('hex');
  return { json: { label: item.json.label || path, path, query, url: base + '/' + path + (qs ? '?' + qs : ''), ts, signature } };
});
"""
NORMALIZE = r"""
// One result per request: { label, path, ok, status, data, error }. Never throws, so callers decide (reports throw -> error
// handler; the health monitor alerts). Transport errors (connection refused, timeout) arrive as { error } via "continue on fail".
const reqs = $('Sign request').all();
return $input.all().map((item, i) => {
  const idx = item.pairedItem && typeof item.pairedItem.item === 'number' ? item.pairedItem.item : i;
  const req = (reqs[idx] || reqs[i] || { json: {} }).json;
  const r = item.json || {};
  const base = { label: req.label, path: req.path, query: req.query };
  if (r.error && r.statusCode === undefined) {
    const msg = typeof r.error === 'string' ? r.error : (r.error.message || JSON.stringify(r.error));
    return { json: { ...base, ok: false, status: 0, error: msg, data: null } };
  }
  let body = r.body !== undefined ? r.body : r.data;
  if (typeof body === 'string') { try { body = JSON.parse(body); } catch (e) { body = null; } }
  const ok = r.statusCode === 200 && !!body && body.success === true;
  const error = ok ? null : ((body && (body.message || body.error)) || ('HTTP ' + r.statusCode));
  return { json: { ...base, ok, status: r.statusCode, error, data: ok ? body.data : null } };
});
"""
api_nodes = [
    sub_trigger(W),
    code(W, 'Sign request', [220, 0], SIGN),
    node(W, 'GET 3alraf API', 'n8n-nodes-base.httpRequest', 4.2, [440, 0], {
        'url': '={{ $json.url }}', 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
        'sendHeaders': True,
        'headerParameters': {'parameters': [
            {'name': 'X-Timestamp', 'value': '={{ $json.ts }}'},
            {'name': 'X-Signature', 'value': '={{ $json.signature }}'},
            {'name': 'Accept', 'value': 'application/json'}]},
        'options': {'timeout': 20000, 'response': {'response': {'fullResponse': True, 'neverError': True, 'responseFormat': 'text'}}},
    }, credentials=CRED, onError='continueRegularOutput', retryOnFail=True, maxTries=2, waitBetweenTries=3000),
    code(W, 'Normalize response', [660, 0], NORMALIZE),
]
api = workflow(W, '91 - 3alraf API request (signed, sub-workflow)', api_nodes,
               link([('When called by another workflow', 'Sign request'), ('Sign request', 'GET 3alraf API'), ('GET 3alraf API', 'Normalize response')]))

# ------------------------------------------------------------------------------------------------ 90 notify owner
W = 'notify'
FORMAT = r"""
// Owner notification. Input items: { title, text, level: info|warning|error, source, link? }.
// Always: the formatted message is this execution's output (Executions list in n8n).
// Optional: NOTIFY_WEBHOOK_URL (POST JSON) and NOTIFY_EMAIL_ENABLED=true + the "Send email" node (enable it, pick an SMTP credential).
const prefix = { info: '[3alraf]', warning: '[3alraf][تنبيه]', error: '[3alraf][خطأ]' };
return $input.all().map((item) => {
  const j = item.json || {};
  const level = ['info', 'warning', 'error'].includes(j.level) ? j.level : 'info';
  const title = String(j.title || 'إشعار');
  const text = String(j.text || '');
  const message = `${prefix[level]} ${title}\n\n${text}` + (j.link ? `\n\n${j.link}` : '');
  return { json: {
    title, text, level, source: j.source || 'unknown', link: j.link || null, message, sent_at: new Date().toISOString(),
    channels: { execution_output: true, webhook: !!$env.NOTIFY_WEBHOOK_URL, email: String($env.NOTIFY_EMAIL_ENABLED || '') === 'true' },
    webhook_url: String($env.NOTIFY_WEBHOOK_URL || ''),
    email_to: String($env.NOTIFY_EMAIL_TO || ''), email_from: String($env.NOTIFY_EMAIL_FROM || ''),
  } };
});
"""
notify_nodes = [
    sub_trigger(W),
    code(W, 'Format message', [220, 0], FORMAT),
    iff(W, 'Webhook configured?', [440, -120], '={{ $json.channels.webhook }}'),
    node(W, 'POST webhook (WhatsApp / Telegram relay)', 'n8n-nodes-base.httpRequest', 4.2, [680, -140], {
        'method': 'POST', 'url': '={{ $json.webhook_url }}', 'sendBody': True, 'specifyBody': 'json',
        'jsonBody': '={{ JSON.stringify({ title: $json.title, text: $json.text, level: $json.level, source: $json.source, link: $json.link, message: $json.message, sent_at: $json.sent_at }) }}',
        'options': {'timeout': 15000}}, onError='continueRegularOutput'),
    iff(W, 'E-mail enabled?', [440, 120], '={{ $json.channels.email }}'),
    node(W, 'Send email (SMTP, disabled)', 'n8n-nodes-base.emailSend', 2.1, [680, 100], {
        'fromEmail': '={{ $json.email_from }}', 'toEmail': '={{ $json.email_to }}', 'subject': '={{ $json.title }}',
        'emailFormat': 'text', 'text': '={{ $json.message }}', 'options': {}}, disabled=True,
        notes='Enable this node and select an SMTP credential to send e-mails (also set NOTIFY_EMAIL_ENABLED=true in n8n/.env).'),
]
notify = workflow(W, '90 - Notify owner (sub-workflow)', notify_nodes, link([
    ('When called by another workflow', 'Format message'), ('Format message', 'Webhook configured?'), ('Format message', 'E-mail enabled?'),
    ('Webhook configured?', 'POST webhook (WhatsApp / Telegram relay)', 0), ('E-mail enabled?', 'Send email (SMTP, disabled)', 0)]),
    error_wf=False)

# ------------------------------------------------------------------------------------------------ 00 error handler
W = 'error'
ERR = r"""
// Error Trigger payload: { execution: { id, url, error, lastNodeExecuted, mode }, workflow: { id, name } }
// (or { trigger: { error }, workflow } when the trigger node itself failed).
const d = $input.first().json;
const wf = d.workflow || {};
const ex = d.execution || {};
const err = ex.error || (d.trigger && d.trigger.error) || {};
const node = ex.lastNodeExecuted || (err.node && err.node.name) || '—';
const lines = [
  `سير العمل: ${wf.name || '?'} (${wf.id || '?'})`,
  `العقدة: ${node}`,
  `الخطأ: ${String(err.message || err.description || 'unknown error').slice(0, 800)}`,
  `التنفيذ: ${ex.id || '—'} (${ex.mode || 'trigger'})`,
  `الوقت: ${new Date().toLocaleString('en-GB', { timeZone: 'Africa/Cairo' })} (القاهرة)`,
];
return [{ json: { title: `فشل سير عمل n8n: ${wf.name || '?'}`, text: lines.join('\n'), level: 'error', source: '00-error-handler', link: ex.url || null } }];
"""
error_nodes = [
    node(W, 'Error Trigger', 'n8n-nodes-base.errorTrigger', 1, [0, 0], {}),
    code(W, 'Build error message', [220, 0], ERR),
    call(W, 'Notify owner', [440, 0], 'notify'),
]
error = workflow(W, '00 - Error handler (notify owner)', error_nodes,
                 link([('Error Trigger', 'Build error message'), ('Build error message', 'Notify owner')]), error_wf=False)

# ------------------------------------------------------------------------------------------------ 01 weekly report
W = 'weekly'
REQ_WEEKLY = r"""
return [
  { json: { label: 'store', path: 'ping' } },
  { json: { label: 'week', path: 'gift-finder/stats', query: { days: 7 } } },
  { json: { label: 'month', path: 'gift-finder/stats', query: { days: 30 } } },
];
"""
WEEKLY = HELPERS + r"""
const res = $input.all().map((i) => i.json);
requireOk(res);
const get = (label) => res.find((r) => r.label === label).data;
const store = get('store').store || {};
const w = get('week');
const m = get('month');
const occ = Object.entries(m.by_occasion || {}).sort((a, b) => b[1] - a[1]).slice(0, 5)
  .map(([k, c]) => `${OCC_AR[k] || k || 'بدون'} ${c}`).join('، ') || '—';
const lines = [
  `المتجر: ${store.name || '—'}`,
  'الفترة: آخر 7 أيام (وبين القوسين آخر 30 يومًا)',
  '',
  `• عمليات البحث: ${num(w.sessions)} (${num(m.sessions)})`,
  `• بحث بنتائج: ${pct(w.completion_rate)} (${pct(m.completion_rate)}) — بدون نتائج: ${num(w.no_result)} (${num(m.no_result)})`,
  `• أضافوا للسلة: ${pct(w.cart_rate)} (${pct(m.cart_rate)})`,
  `• طلبات من مكتشف الهدايا: ${num(w.orders)} (${num(m.orders)}) بقيمة ${money(w.orders_revenue)} (${money(m.orders_revenue)})`,
  `• متوسط قيمة الطلب: ${money(w.orders_aov)} (${money(m.orders_aov)}) — تحويل السلة لطلب: ${pct(w.order_rate)} (${pct(m.order_rate)})`,
  `• متوسط الميزانية: ${money(w.avg_budget)} (${money(m.avg_budget)})`,
  `• أكثر المناسبات بحثًا (30 يومًا): ${occ}`,
];
const hints = [];
if (!w.enabled) hints.push('مكتشف الهدايا مُعطّل من الإعدادات العامة — لا يظهر للعملاء.');
if (w.sessions > 0 && w.no_result / w.sessions >= 0.2) hints.push('أكثر من 20% من عمليات البحث بلا نتائج: راجع وسوم الهدايا والميزانيات.');
if (w.sessions === 0) hints.push('لا توجد عمليات بحث هذا الأسبوع: تأكد من ظهور الرابط في القائمة والصفحة الرئيسية.');
if (hints.length) lines.push('', 'ملاحظات:', ...hints.map((h) => '- ' + h));
return [{ json: { title: 'تقرير مكتشف الهدايا الأسبوعي', text: lines.join('\n'), level: w.enabled ? 'info' : 'warning',
  source: '01-gift-finder-weekly-report', link: SITE + '/admin/gift-finder', week: w, month: m } }];
"""
weekly_nodes = [
    manual(W),
    schedule(W, 'Every Saturday 10:00 (Cairo)', [0, 0], '0 10 * * 6'),
    code(W, 'Requests', [220, 0], REQ_WEEKLY),
    call(W, 'Call 3alraf API', [440, 0], 'api'),
    code(W, 'Build Arabic summary', [660, 0], WEEKLY),
    call(W, 'Notify owner', [880, 0], 'notify'),
]
weekly = workflow(W, '01 - Gift finder weekly report', weekly_nodes, link([
    ('Every Saturday 10:00 (Cairo)', 'Requests'), ('Run manually', 'Requests'), ('Requests', 'Call 3alraf API'), ('Call 3alraf API', 'Build Arabic summary'),
    ('Build Arabic summary', 'Notify owner')]))

# ------------------------------------------------------------------------------------------------ 02 untagged alert
W = 'untagged'
UNTAGGED = HELPERS + r"""
const MAX_LINES = 25;
const res = $input.all().map((i) => i.json);
requireOk(res);
const d = res[0].data;
const c = d.counts || {};
const total = (c.products || 0) + (c.bundles || 0);
if (!total) {
  return [{ json: { notify: false, summary: `كل المنتجات (${num(c.active_products)}) والباقات (${num(c.active_bundles)}) النشطة عليها وسوم هدايا.`, counts: c } }];
}
const lines = [`منتجات نشطة بدون وسم هدية: ${num(c.products)} من ${num(c.active_products)}`,
  `باقات بدون أي منتج موسوم: ${num(c.bundles)} من ${num(c.active_bundles)}`, ''];
if ((d.products || []).length) {
  lines.push('المنتجات:');
  d.products.slice(0, MAX_LINES).forEach((p) => lines.push(`- ${p.name}${p.sku ? ' [' + p.sku + ']' : ''}${p.gift_suitable === false ? ' (غير مناسب كهدية)' : ''}: ${SITE}/admin/products/${p.id}/edit`));
  if (c.products > MAX_LINES) lines.push(`… و${num(c.products - MAX_LINES)} منتج آخر`);
  lines.push('');
}
if ((d.bundles || []).length) {
  lines.push('الباقات:');
  d.bundles.slice(0, MAX_LINES).forEach((b) => lines.push(`- ${b.name} (${num(b.items)} منتجات): ${SITE}/admin/bundles/${b.id}/edit`));
  if (c.bundles > MAX_LINES) lines.push(`… و${num(c.bundles - MAX_LINES)} باقة أخرى`);
  lines.push('');
}
lines.push(`أسرع طريقة: صفحة وسوم الهدايا (فيها زر الاقتراح التلقائي): ${SITE}/admin/gift-finder/tags`);
return [{ json: { notify: true, title: `منتجات/باقات تحتاج وسوم هدايا (${num(total)})`, text: lines.join('\n'), level: 'warning',
  source: '02-gift-untagged-alert', link: SITE + '/admin/gift-finder/tags', counts: c } }];
"""
untagged_nodes = [
    manual(W),
    schedule(W, 'Every Saturday 10:30 (Cairo)', [0, 0], '30 10 * * 6'),
    code(W, 'Request', [220, 0], "return [{ json: { label: 'untagged', path: 'gift-finder/untagged', query: { limit: 100 } } }];"),
    call(W, 'Call 3alraf API', [440, 0], 'api'),
    code(W, 'Build message', [660, 0], UNTAGGED),
    iff(W, 'Anything untagged?', [880, 0], '={{ $json.notify }}'),
    call(W, 'Notify owner', [1100, -100], 'notify'),
    noop(W, 'All tagged', [1100, 100]),
]
untagged = workflow(W, '02 - Gift finder untagged products/bundles alert', untagged_nodes, link([
    ('Every Saturday 10:30 (Cairo)', 'Request'), ('Run manually', 'Request'), ('Request', 'Call 3alraf API'), ('Call 3alraf API', 'Build message'),
    ('Build message', 'Anything untagged?'), ('Anything untagged?', 'Notify owner', 0), ('Anything untagged?', 'All tagged', 1)]))

# ------------------------------------------------------------------------------------------------ 03 occasion reminder
W = 'occasion'
OCCASION = HELPERS + r"""
// Reminder when a gift occasion is exactly 21, 7 or 2 days away (runs daily). Hijri dates (Umm al-Qura) are approximate.
const REMIND_AT = [21, 7, 2];
const res = $input.all().map((i) => i.json);
requireOk(res);
const d = res[0].data;
const all = d.occasions || [];
const due = all.filter((o) => REMIND_AT.includes(o.days_left));
const when = (n) => n === 1 ? 'غدًا' : n === 2 ? 'بعد يومين' : n <= 10 ? `بعد ${n} أيام` : `بعد ${n} يومًا`;
const label = (o) => (o.label && o.label.ar) || OCC_AR[o.key] || o.key;
const upcoming = all.map((o) => `${label(o)} ${o.date} (${when(o.days_left)})${o.approximate ? ' ~' : ''}`).join('، ') || '—';
if (!due.length) {
  return [{ json: { notify: false, summary: `لا تذكير اليوم (${d.today}). القادم خلال ${d.days} يومًا: ${upcoming}`, occasions: all } }];
}
const lines = [];
due.forEach((o) => {
  lines.push(`• ${label(o)}: ${when(o.days_left)} — ${o.date}`);
  if (o.approximate) lines.push('  تاريخ هجري تقريبي (تقويم أم القرى) وقد يختلف يومًا أو يومين حسب رؤية الهلال.');
});
lines.push('', 'مقترح:',
  `- راجع وسوم الهدايا لهذه المناسبة: ${SITE}/admin/gift-finder/tags`,
  `- جهّز باقة أو عرضًا وبانر للمناسبة: ${SITE}/admin/bundles`,
  `- إحصائيات مكتشف الهدايا: ${SITE}/admin/gift-finder`,
  '', `كل المناسبات خلال ${d.days} يومًا: ${upcoming}`);
const soonest = Math.min(...due.map((o) => o.days_left));
return [{ json: { notify: true, title: `تذكير مناسبة هدايا: ${due.map(label).join(' و')}`, text: lines.join('\n'),
  level: soonest <= 2 ? 'warning' : 'info', source: '03-gift-occasion-reminder', link: SITE + '/admin/gift-finder', occasions: due } }];
"""
occasion_nodes = [
    manual(W),
    schedule(W, 'Every day 09:00 (Cairo)', [0, 0], '0 9 * * *'),
    code(W, 'Request', [220, 0], "return [{ json: { label: 'occasions', path: 'gift-finder/occasions', query: { days: 21 } } }];"),
    call(W, 'Call 3alraf API', [440, 0], 'api'),
    code(W, 'Pick occasions 21 / 7 / 2 days away', [660, 0], OCCASION),
    iff(W, 'Reminder due?', [880, 0], '={{ $json.notify }}'),
    call(W, 'Notify owner', [1100, -100], 'notify'),
    noop(W, 'Nothing today', [1100, 100]),
]
occasion = workflow(W, '03 - Gift occasion reminder', occasion_nodes, link([
    ('Every day 09:00 (Cairo)', 'Request'), ('Run manually', 'Request'), ('Request', 'Call 3alraf API'), ('Call 3alraf API', 'Pick occasions 21 / 7 / 2 days away'),
    ('Pick occasions 21 / 7 / 2 days away', 'Reminder due?'), ('Reminder due?', 'Notify owner', 0), ('Reminder due?', 'Nothing today', 1)]))

# ------------------------------------------------------------------------------------------------ 04 health monitor
W = 'health'
HEALTH = HELPERS + r"""
// Alerts on the first failed check, then once an hour (every 4th check) while it stays down, and once on recovery.
// State lives in workflow static data, which n8n persists for trigger (production) runs only — a manual run never alerts on recovery.
const r = $input.first().json || {};
const d = r.data || {};
const healthy = r.ok === true && d.status === 'ok' && d.database === true;
const state = $getWorkflowStaticData('global');
const prev = state.healthy;
state.healthy = healthy;
state.checked_at = new Date().toISOString();
let notify = false, level = 'info', title = '', text = '';
if (!healthy) {
  state.failures = (state.failures || 0) + 1;
  notify = prev !== false || state.failures % 4 === 0;
  level = 'error';
  title = 'متجر 3alraf لا يستجيب بشكل سليم';
  const why = r.ok ? `الحالة: ${d.status}، قاعدة البيانات: ${d.database ? 'تعمل' : 'لا تعمل'}` : `خطأ: ${r.status || 'no response'} ${r.error || ''}`;
  text = [why, `فحوصات فاشلة متتالية: ${state.failures} (كل 15 دقيقة)`, `منذ: ${state.down_since || state.checked_at}`].join('\n');
  state.down_since = state.down_since || state.checked_at;
} else {
  if (prev === false) {
    notify = true;
    title = 'عاد متجر 3alraf للعمل';
    text = `توقف منذ ${state.down_since} — عدد الفحوصات الفاشلة: ${state.failures}`;
  }
  state.failures = 0;
  delete state.down_since;
}
return [{ json: { notify, healthy, title, text, level, source: '04-health-monitor', link: SITE || null,
  check: { status: r.status, ok: r.ok, data: r.data, error: r.error }, gift_finder_enabled: d.gift_finder_enabled } }];
"""
health_nodes = [
    manual(W),
    schedule(W, 'Every 15 minutes', [0, 0], '*/15 * * * *'),
    code(W, 'Request', [220, 0], "return [{ json: { label: 'health', path: 'health' } }];"),
    call(W, 'Call 3alraf API', [440, 0], 'api', onError='continueRegularOutput'),
    code(W, 'Evaluate health', [660, 0], HEALTH),
    iff(W, 'Alert or recovery?', [880, 0], '={{ $json.notify }}'),
    call(W, 'Notify owner', [1100, -100], 'notify'),
    noop(W, 'Healthy / already alerted', [1100, 100]),
]
health = workflow(W, '04 - 3alraf health monitor', health_nodes, link([
    ('Every 15 minutes', 'Request'), ('Run manually', 'Request'), ('Request', 'Call 3alraf API'), ('Call 3alraf API', 'Evaluate health'),
    ('Evaluate health', 'Alert or recovery?'), ('Alert or recovery?', 'Notify owner', 0), ('Alert or recovery?', 'Healthy / already alerted', 1)]))

FILES = {
    '00-error-handler.json': error, '01-gift-finder-weekly-report.json': weekly, '02-gift-untagged-alert.json': untagged,
    '03-gift-occasion-reminder.json': occasion, '04-health-monitor.json': health, '90-notify-owner.json': notify,
    '91-api-request.json': api,
}
os.makedirs(OUT, exist_ok=True)
for f, wf in FILES.items():
    with open(os.path.join(OUT, f), 'w', encoding='utf-8') as fh:
        json.dump(wf, fh, ensure_ascii=False, indent=2)
        fh.write('\n')
print('\n'.join(FILES))
