// Used by `php artisan growth:n8n-selftest`: executes the "Sign ..." Code nodes of every workflow JSON exactly as n8n would (same JS, same HMAC),
// with stubbed trigger data, and prints the HTTP requests they build as JSON. Needs node >= 18. Env: GROWTH_API_BASE, GROWTH_HMAC_SECRET.
const fs = require('fs');
const path = require('path');
const dir = process.argv[2];
const SAMPLE = { channel: 'whatsapp', external_id: 'selftest-1', thread_id: 'selftest-thread', text: 'How much is this?', language: 'en', consent: true, opted_out: false, received_at: new Date().toISOString(), product_id: 1, id: 1, type: 'note', title: 'Selftest', summary: 'Selftest', status: 'accepted', rating: 5, event_id: 'selftest-evt' };
const sample = (key) => new Proxy(function () {}, {
  get: (_t, k) => {
    if (k === Symbol.toPrimitive) return () => String(SAMPLE[key] ?? '1');
    if (k === 'toJSON') return () => (key in SAMPLE ? SAMPLE[key] : 'selftest');
    if (k === 'first' || k === 'last') return () => ({ json: sample('') });
    if (k === 'length') return 1;
    if (typeof k === 'symbol') return undefined;
    return sample(k);
  },
  apply: () => sample(key),
});
const out = [];
for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.json')).sort()) {
  const wf = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
  for (const n of wf.nodes) {
    if (n.type !== 'n8n-nodes-base.code' || !/^Sign /.test(n.name)) continue;
    const dollar = () => ({ first: () => ({ json: sample('') }), all: () => [{ json: sample('') }], item: { json: sample('') } });
    try {
      const fn = new Function('$env', '$', '$json', 'require', n.parameters.jsCode);
      const r = fn(process.env, dollar, sample(''), require);
      const j = r[0].json;
      out.push({ workflow: f, node: n.name, method: j.method, url: j.url, body: j.body, headers: j.headers });
    } catch (e) {
      out.push({ workflow: f, node: n.name, error: String(e.message) });
    }
  }
}
process.stdout.write(JSON.stringify(out));
