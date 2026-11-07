<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Exceptions\AccountingException;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Posting\DocumentPoster;
use App\Support\ErpRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JournalController extends ErpController
{
    /** Sub-ledger / clearing control accounts: manual and adjusting journals may not touch them (opening journals may). */
    public const CONTROL_ROLES = ['receivable', 'payable', 'inventory', 'inventory_transit', 'goods_in_delivery', 'vat_payable', 'carrier_receivable',
        'affiliate_payable', 'customer_advances', 'supplier_advances', 'distributions_payable'];

    public function index(Request $r)
    {
        $this->allow('erp.journals.view');
        $this->ensureSetup();
        // QA-008: list filters are sanitised (arrays / garbage are ignored, never a 500)
        $str = fn (string $k, int $max = 100) => is_string($v = $r->query($k)) && $v !== '' ? mb_substr($v, 0, $max) : null;
        $date = fn (string $k) => ($v = $str($k, 10)) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        $status = in_array($s = $str('status', 10), ['draft', 'posted', 'reversed'], true) ? $s : null;
        $branch = ($b = $str('branch_id', 18)) && ctype_digit($b) ? (int) $b : null;
        $term = $str('q');
        $q = JournalEntry::query()->when($status, fn ($q, $s) => $q->where('status', $s))->when($date('from'), fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($date('to'), fn ($q, $d) => $q->where('entry_date', '<=', $d))->when($branch, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($term, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('description', 'like', '%'.addcslashes($s, '%_\\').'%')));

        return view('admin.erp.journals', ['entries' => $q->latest('entry_date')->latest('id')->paginate(25)->withQueryString(),
            'flt' => ['q' => $term, 'status' => $status, 'from' => $date('from'), 'to' => $date('to')]]);
    }

    public function create()
    {
        $this->allow('erp.journals.create');
        $this->ensureSetup();

        return view('admin.erp.journal-form', ['accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get()]);
    }

    public function store(Request $r, JournalService $svc)
    {
        $this->allow('erp.journals.create');
        $d = $r->validate([
            'entry_date' => ErpRules::date(), 'description' => ['required', 'string', 'max:500'], 'type' => ['required', 'in:manual,adjusting,opening'],
            'branch_id' => ['nullable', 'integer', $this->existsRule('branches', true)], 'post_now' => ['nullable', 'boolean'], 'ref' => ['nullable', 'string', 'max:60'],
            'lines' => ['required', 'array', 'min:2', 'max:500'], 'lines.*.account_id' => ['required', 'integer', 'max_digits:18'],
            'lines.*.debit' => ErpRules::money(false, false), 'lines.*.credit' => ErpRules::money(false, false), 'lines.*.memo' => ['nullable', 'string', 'max:300'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);
        $ids = collect($d['lines'])->pluck('account_id')->unique();
        abort_unless(Account::whereIn('id', $ids)->count() === $ids->count(), 404); // unknown account id
        $total = collect($d['lines'])->reduce(fn ($c, $l) => bcadd($c, ErpRules::amount($l['debit'] ?? 0), 4), '0');
        if (bccomp($total, '99999999999999.99', 4) > 0) { // QA-006: the entry total must fit decimal(18,4)
            return back()->withInput()->withErrors(['lines' => __('erp_acc.errors.total_too_large')]);
        }
        if ($d['type'] !== 'opening' && ($ctl = Account::whereIn('id', $ids)->whereIn('role_key', self::CONTROL_ROLES)->pluck('code')->all())) {
            // ERP-017: sub-ledger control accounts are moved only by their documents / party forms (party is required there)
            return back()->withInput()->withErrors(['erp' => __('erp_acc.errors.control_account', ['codes' => implode(', ', $ctl)])]);
        }
        $post = ! empty($d['post_now']);
        if ($post) {
            $this->allow('erp.journals.post');
            if (app(ErpSettings::class)->bool('journal_requires_second_poster')) {
                return back()->withInput()->withErrors(['erp' => __('erp_acc.errors.second_poster')]);
            }
        }
        $lines = collect($d['lines'])->map(fn ($l) => ['account' => (int) $l['account_id'], 'debit' => ErpRules::amount($l['debit'] ?? 0), 'credit' => ErpRules::amount($l['credit'] ?? 0), 'memo' => $l['memo'] ?? null])->all();
        $data = ['entry_date' => $d['entry_date'], 'description' => $d['description'], 'type' => $d['type'], 'branch_id' => $d['branch_id'] ?? null, 'created_by' => auth('admin')->id()];
        // QA-013: the form carries a one-time token; a repeated submit returns the entry already saved (a changed payload is refused, QA-012)
        if (! empty($d['ref'])) {
            $data['idempotency_key'] = 'manual:'.$d['ref'];
            $data['strict_replay'] = true;
            if ($done = JournalEntry::where('idempotency_key', $data['idempotency_key'])->first()) {
                return $this->guarded(function () use ($svc, $done, $lines) {
                    $svc->assertSameReplay($done, $lines);

                    return redirect()->route('admin.erp.journals.show', $done->id)->with('status', __('erp_acc.already_submitted'));
                });
            }
        }
        if ($r->hasFile('attachment')) { // ERP-017: supporting document, private disk, served only through the permission-checked route
            $data['attachment'] = $r->file('attachment')->store('erp/journals', 'private');
        }

        return $this->guarded(function () use ($svc, $data, $lines, $post) {
            $e = $post ? $svc->post($data, $lines) : $svc->draft($data, $lines);

            return redirect()->route('admin.erp.journals.show', $e->id)->with('status', __('erp.saved'));
        });
    }

    public function show(Request $r, int $id)
    {
        $this->allow('erp.journals.view');
        if ($r->query('download') === 'attachment') {
            return $this->attachment($id);
        }

        return view('admin.erp.journal-show', ['entry' => JournalEntry::with(['lines.account', 'lines.branch', 'branch', 'period', 'reverses'])->findOrFail($id)]);
    }

    /** ERP-017: download the supporting document of an entry (same permission as viewing the entry; ?download=attachment). */
    private function attachment(int $id)
    {
        $e = JournalEntry::findOrFail($id);
        abort_unless($e->attachment && Storage::disk('private')->exists($e->attachment), 404);

        return Storage::disk('private')->download($e->attachment, $e->number.'.'.pathinfo($e->attachment, PATHINFO_EXTENSION));
    }

    public function post(int $id, JournalService $svc)
    {
        $this->allow('erp.journals.post');

        return $this->guarded(function () use ($id, $svc) {
            $entry = JournalEntry::findOrFail($id);
            if (app(ErpSettings::class)->bool('journal_requires_second_poster') && $entry->created_by && (int) $entry->created_by === (int) auth('admin')->id()) {
                throw new AccountingException(__('erp_acc.errors.second_poster'));
            }
            $svc->postDraft($entry, auth('admin')->id());

            return back()->with('status', __('erp.posted'));
        });
    }

    public function reverse(Request $r, int $id, JournalService $svc)
    {
        $this->allow('erp.journals.reverse');
        $d = $r->validate(['reason' => ['required', 'string', 'max:300'], 'date' => ErpRules::date(false)]);

        return $this->guarded(function () use ($id, $svc, $d) {
            $entry = JournalEntry::findOrFail($id);
            if (DocumentPoster::isDocumentEntry($entry)) { // ERP-007: an auto entry mirrors a live document; reverse it via the document (cancel...) or repost it
                throw new AccountingException(__('erp_acc.errors.auto_reverse_forbidden'));
            }
            $rev = $svc->reverse($entry, auth('admin')->id(), $d['date'] ?? null, $d['reason']);

            return redirect()->route('admin.erp.journals.show', $rev->id)->with('status', __('erp.reversed'));
        });
    }

    /** Corrected re-post of an auto document entry: reverse its family and post it again from the document's current data (versioned key). */
    public function repost(Request $r, int $id, DocumentPoster $docs)
    {
        $this->allow('erp.journals.reverse');
        $this->allow('erp.journals.post');
        $d = $r->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->guarded(function () use ($id, $docs, $d) {
            $new = $docs->repost(JournalEntry::findOrFail($id), auth('admin')->id(), $d['reason']);

            return redirect()->route('admin.erp.journals.show', $new?->id ?? $id)->with('status', __('erp_acc.reposted'));
        });
    }

    public function destroy(int $id, JournalService $svc)
    {
        $this->allow('erp.journals.create');

        return $this->guarded(function () use ($id, $svc) {
            $svc->deleteDraft(JournalEntry::findOrFail($id));

            return redirect()->route('admin.erp.journals.index')->with('status', __('erp.deleted'));
        });
    }
}
