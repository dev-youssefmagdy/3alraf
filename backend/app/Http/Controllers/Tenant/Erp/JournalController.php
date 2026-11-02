<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Services\Erp\Accounting\JournalService;
use Illuminate\Http\Request;

class JournalController extends ErpController
{
    public function index(Request $r)
    {
        $this->allow('erp.journals.view');
        $this->ensureSetup();
        $q = JournalEntry::query()->when($r->status, fn ($q, $s) => $q->where('status', $s))->when($r->from, fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($r->to, fn ($q, $d) => $q->where('entry_date', '<=', $d))->when($r->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($r->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('description', 'like', "%$s%")));

        return view('tenant.erp.journals', ['entries' => $q->latest('entry_date')->latest('id')->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        $this->allow('erp.journals.create');
        $this->ensureSetup();

        return view('tenant.erp.journal-form', ['accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get()]);
    }

    public function store(Request $r, JournalService $svc)
    {
        $this->allow('erp.journals.create');
        $d = $r->validate([
            'entry_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:500'], 'type' => ['required', 'in:manual,adjusting,opening'],
            'branch_id' => ['nullable', 'integer', $this->tenantExists('branches', true)], 'post_now' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', 'integer'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'], 'lines.*.memo' => ['nullable', 'string', 'max:300'],
        ]);
        $ids = collect($d['lines'])->pluck('account_id')->unique();
        abort_unless(Account::whereIn('id', $ids)->count() === $ids->count(), 404);
        $post = ! empty($d['post_now']);
        if ($post) {
            $this->allow('erp.journals.post');
        }
        $lines = collect($d['lines'])->map(fn ($l) => ['account' => (int) $l['account_id'], 'debit' => (string) ($l['debit'] ?? 0) ?: '0', 'credit' => (string) ($l['credit'] ?? 0) ?: '0', 'memo' => $l['memo'] ?? null])->all();
        $data = ['entry_date' => $d['entry_date'], 'description' => $d['description'], 'type' => $d['type'], 'branch_id' => $d['branch_id'] ?? null, 'created_by' => auth('tenant')->id()];

        return $this->guarded(function () use ($svc, $data, $lines, $post) {
            $e = $post ? $svc->post($data, $lines) : $svc->draft($data, $lines);

            return redirect()->route('tenant.erp.journals.show', $e->id)->with('status', __('erp.saved'));
        });
    }

    public function show(int $id)
    {
        $this->allow('erp.journals.view');

        return view('tenant.erp.journal-show', ['entry' => JournalEntry::with(['lines.account', 'lines.branch', 'branch', 'period', 'reverses'])->findOrFail($id)]);
    }

    public function post(int $id, JournalService $svc)
    {
        $this->allow('erp.journals.post');

        return $this->guarded(function () use ($id, $svc) {
            $svc->postDraft(JournalEntry::findOrFail($id), auth('tenant')->id());

            return back()->with('status', __('erp.posted'));
        });
    }

    public function reverse(Request $r, int $id, JournalService $svc)
    {
        $this->allow('erp.journals.reverse');
        $d = $r->validate(['reason' => ['required', 'string', 'max:300'], 'date' => ['nullable', 'date']]);

        return $this->guarded(function () use ($id, $svc, $d) {
            $rev = $svc->reverse(JournalEntry::findOrFail($id), auth('tenant')->id(), $d['date'] ?? null, $d['reason']);

            return redirect()->route('tenant.erp.journals.show', $rev->id)->with('status', __('erp.reversed'));
        });
    }

    public function destroy(int $id, JournalService $svc)
    {
        $this->allow('erp.journals.create');

        return $this->guarded(function () use ($id, $svc) {
            $svc->deleteDraft(JournalEntry::findOrFail($id));

            return redirect()->route('tenant.erp.journals.index')->with('status', __('erp.deleted'));
        });
    }
}
