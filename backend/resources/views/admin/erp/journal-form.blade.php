<x-layouts.admin :title="__('erp.j.new')">
    @include('admin.erp._flash')
    <x-card>
        <form method="POST" action="{{ route('admin.erp.journals.store') }}" id="jf" enctype="multipart/form-data">
            @csrf<input type="hidden" name="ref" value="{{ old('ref', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="mb-4 grid gap-3 md:grid-cols-4 text-sm">
                <label>{{ __('erp.f.date') }}<input type="date" name="entry_date" required min="{{ \App\Support\ErpRules::MIN_DATE }}" max="{{ \App\Support\ErpRules::maxDate() }}" value="{{ old('entry_date', now()->toDateString()) }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
                <label>{{ __('erp.f.type') }}<select name="type" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5">@foreach (['manual', 'adjusting', 'opening'] as $t)<option value="{{ $t }}">{{ __('erp.entry_types.'.$t) }}</option>@endforeach</select></label>
                <label class="md:col-span-2">{{ __('erp.f.description') }}<input name="description" required maxlength="500" value="{{ old('description') }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
                <label class="md:col-span-2">{{ __('erp_acc.attachment') }}<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm file:me-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1"><span class="mt-1 block text-xs text-slate-500">{{ __('erp_acc.attachment_hint') }}</span></label>
            </div>
            <table class="w-full text-sm" id="lines">
                <thead><tr class="text-slate-500"><th class="text-start">{{ __('erp.f.account') }}</th><th class="w-36 text-start">{{ __('erp.f.debit') }}</th><th class="w-36 text-start">{{ __('erp.f.credit') }}</th><th class="text-start">{{ __('erp.f.memo') }}</th></tr></thead>
                <tbody>
                @for ($i = 0; $i < 2; $i++)
                    <tr>
                        <td><select name="lines[{{ $i }}][account_id]" required class="w-full rounded border border-slate-300 px-2 py-1.5">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></td>
                        <td><input type="number" step="0.01" min="0" max="99999999999999.99" name="lines[{{ $i }}][debit]" class="dr w-full rounded border border-slate-300 px-2 py-1.5"></td>
                        <td><input type="number" step="0.01" min="0" max="99999999999999.99" name="lines[{{ $i }}][credit]" class="cr w-full rounded border border-slate-300 px-2 py-1.5"></td>
                        <td><input name="lines[{{ $i }}][memo]" class="w-full rounded border border-slate-300 px-2 py-1.5"></td>
                    </tr>
                @endfor
                </tbody>
            </table>
            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                <button type="button" id="addline" class="btn-sm btn-secondary">{{ __('erp.j.add_line') }}</button>
                <span>{{ __('erp.f.debit') }}: <b id="sdr">0.00</b></span><span>{{ __('erp.f.credit') }}: <b id="scr">0.00</b></span><span id="diff" class="font-semibold"></span>
            </div>
            <div class="mt-5 flex items-center gap-4">
                @can('erp.journals.post')<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="post_now" value="1"> {{ __('erp.j.post_now') }}</label>@endcan
                <x-button type="submit">{{ __('erp.j.save_draft') }}</x-button>
            </div>
        </form>
    </x-card>
    <script>
    (function () {
        const body = document.querySelector('#lines tbody');
        const calc = () => {
            let d = 0, c = 0; body.querySelectorAll('tr').forEach(r => { d += +r.querySelector('.dr').value || 0; c += +r.querySelector('.cr').value || 0; });
            document.getElementById('sdr').textContent = d.toFixed(2); document.getElementById('scr').textContent = c.toFixed(2);
            const el = document.getElementById('diff'), ok = Math.abs(d - c) < 0.005 && d > 0;
            el.textContent = ok ? @json(__('erp.j.balanced')) : @json(__('erp.j.diff')) + ': ' + Math.abs(d - c).toFixed(2); el.className = 'font-semibold ' + (ok ? 'text-emerald-600' : 'text-red-600');
        };
        body.addEventListener('input', calc);
        document.getElementById('addline').addEventListener('click', () => { const r = body.rows[0].cloneNode(true), n = body.rows.length; r.querySelectorAll('input,select').forEach(e => { e.name = e.name.replace(/\[\d+\]/, '[' + n + ']'); if (e.tagName === 'INPUT') e.value = ''; }); body.appendChild(r); });
        calc();
    })();
    </script>
</x-layouts.admin>
