{{-- Living style guide (/admin/_ui, local + testing only): every x-ui.* component and variant of the panel design system. Panel and theme switchers are client-side only. --}}
@php
    $spark = [4, 6, 5, 8, 7, 10, 9, 12];
    $langs = \App\Models\Language::activeList();
    $order = ['number' => '10458', 'customer_name' => 'أحمد محمود', 'total' => '1,250 ج.م', 'status' => 'pending', 'placed_at' => now()];
    $product = ['name' => 'فستان صيفي مزهر', 'sku' => 'DR-1042', 'price' => '350 ج.م', 'stock' => 12];
    $chart = ['type' => 'line', 'labels' => ['1', '5', '10', '15', '20', '25', '30'], 'datasets' => [['label' => 'A', 'data' => [12, 18, 14, 22, 19, 26, 24]], ['label' => 'B', 'data' => [8, 11, 13, 12, 15, 14, 18]]]];
    $donut = ['type' => 'doughnut', 'labels' => ['Completed', 'Processing', 'Pending', 'Cancelled'], 'datasets' => [['data' => [45, 30, 15, 10]]]];
    $tblCols = [['data' => 'n', 'title' => 'Order'], ['data' => 'c', 'title' => 'Customer'], ['data' => 'a', 'title' => 'Total', 'type' => 'money']];
    $tblRows = collect(range(1, 18))->map(fn ($i) => ['n' => '#'.(10400 + $i), 'c' => 'Customer '.$i, 'a' => $i * 117.5])->all();
    $sections = ['actions' => 'Actions & text', 'forms' => 'Forms', 'feedback' => 'Feedback & overlays', 'data' => 'Data display', 'tables' => 'Tables', 'shell' => 'Shell & navigation'];
@endphp
<x-layouts.admin title="UI kit">
    <x-slot:header>
        <x-ui.page-header :title="__('ui.guide.title')" :subtitle="__('ui.guide.intro')">
            <div class="flex flex-wrap items-center gap-2" data-guide-switch>
                <span class="text-xs font-bold text-panel-text-muted">{{ __('ui.guide.panel') }}</span>
                @foreach (['admin', 'marketer', 'affiliate'] as $pn)<x-ui.chip data-guide-panel="{{ $pn }}" :selected="$pn === 'admin'">{{ ucfirst($pn) }}</x-ui.chip>@endforeach
                <span class="ms-2 text-xs font-bold text-panel-text-muted">{{ __('ui.guide.theme') }}</span>
                <x-ui.chip data-guide-theme="light" :selected="true">{{ __('ui.guide.light') }}</x-ui.chip><x-ui.chip data-guide-theme="dark">{{ __('ui.guide.dark') }}</x-ui.chip>
            </div>
        </x-ui.page-header>
        <x-ui.tabs :tabs="collect($sections)->map(fn ($l, $k) => ['label' => $l, 'href' => '#'.$k, 'key' => $k])->values()->all()" class="mb-6" />
    </x-slot:header>

    <div class="space-y-10">
        {{-- ============ ACTIONS & TEXT ============ --}}
        <section id="actions" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="01">Actions &amp; text</x-ui.heading>
            <x-ui.card title="Button" subtitle="primary / secondary / ghost / danger / sun, sizes sm / md / lg, states default / disabled / loading, with icon">
                <div class="flex flex-wrap items-center gap-3">
                    @foreach (['primary', 'secondary', 'ghost', 'danger', 'sun'] as $v)<x-ui.button :variant="$v">{{ ucfirst($v) }}</x-ui.button>@endforeach
                    <x-ui.button size="sm">Small</x-ui.button><x-ui.button size="lg">Large</x-ui.button>
                    <x-ui.button icon="plus">With icon</x-ui.button><x-ui.button icon="arrow-end" iconPosition="end" variant="secondary">Icon end</x-ui.button>
                    <x-ui.button disabled>Disabled</x-ui.button><x-ui.button :loading="true">Loading</x-ui.button><x-ui.button variant="secondary" :loading="true">Loading</x-ui.button>
                    <x-ui.button href="#actions" variant="secondary">As link</x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card title="IconButton" subtitle="ghost / soft / filled / outline, sm (36, desktop) and md (44), badge">
                <div class="flex flex-wrap items-center gap-3">
                    @foreach (['ghost', 'soft', 'filled', 'outline'] as $v)<x-ui.icon-button icon="bell" label="Bell" :variant="$v" /><x-ui.icon-button icon="edit" label="Edit" :variant="$v" size="sm" />@endforeach
                    <x-ui.icon-button icon="cart" label="Cart" :badge="3" variant="outline" />
                </div>
            </x-ui.card>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card title="Link"><div class="space-y-3"><p class="text-sm">Body copy with an <x-ui.link href="#">inline link</x-ui.link> inside.</p><x-ui.link href="#" style="standalone">Standalone link</x-ui.link><br><x-ui.link href="#" style="subtle">Subtle link</x-ui.link><br><x-ui.link href="#" :external="true">External link</x-ui.link></div></x-ui.card>
                <x-ui.card title="Heading"><div class="space-y-3"><x-ui.heading :level="1" eyebrow="Overline">Heading 1<x-slot:subtitle>Subtitle text</x-slot:subtitle></x-ui.heading><x-ui.heading :level="2">Heading 2</x-ui.heading><x-ui.heading :level="3">Heading 3</x-ui.heading></div></x-ui.card>
            </div>
            <x-ui.card title="Icon" subtitle="x-ui.icon, 24 grid, 1.8 stroke; directional icons mirror in RTL">
                <div class="grid grid-cols-5 gap-3 sm:grid-cols-8 lg:grid-cols-12">@foreach (array_keys(\App\Support\Ui\Icons::all()) as $n)<div class="flex flex-col items-center gap-1 text-center"><x-ui.icon :name="$n" class="size-6 text-panel-text" /><span class="w-full truncate text-[10px] text-panel-text-subtle">{{ $n }}</span></div>@endforeach</div>
            </x-ui.card>
        </section>

        {{-- ============ FORMS ============ --}}
        <section id="forms" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="02">Forms</x-ui.heading>
            <form data-guide-form onsubmit="return false" class="space-y-4">
                <x-ui.form-section title="Field kinds" description="Label above, helper below, error replaces helper. 44px controls.">
                    <x-ui.input name="g_text" label="Text input" help="Helper text" placeholder="Type here" />
                    <x-ui.input name="g_err" label="Error state" error="This field is required" required />
                    <x-ui.input name="g_dis" label="Disabled" disabled value="Read only value" />
                    <x-ui.input name="g_icon" label="With icon + suffix" icon="mail" suffix="EGP" />
                    <x-ui.number name="g_num" label="Number" value="1250.50" />
                    <x-ui.password name="g_pw" label="Password" />
                    <x-ui.search name="g_q" label="Search" />
                    <x-ui.date name="g_date" label="Date" />
                    <x-ui.phone name="g_phone" label="Phone" />
                    <x-ui.select name="g_sel" label="Select" :options="['a' => 'Option A', 'b' => 'Option B', 'c' => 'Option C']" placeholder="Choose" />
                    <x-ui.select name="g_ss" label="Searchable select" :searchable="true" :options="['a' => 'Cairo', 'b' => 'Alexandria', 'c' => 'Giza', 'd' => 'Luxor']" />
                    <x-ui.multiselect name="g_ms" label="Multi select" :options="['a' => 'Red', 'b' => 'Green', 'c' => 'Blue']" :value="['a', 'c']" />
                    <div data-span-all><x-ui.textarea name="g_ta" label="Textarea" rows="3" help="Up to 500 characters" /></div>
                </x-ui.form-section>
                <x-ui.form-section title="Choices" description="Checkbox, radio and switch rows are 44px high.">
                    <div><x-ui.checkbox name="g_c1" label="Checked" :checked="true" /><x-ui.checkbox name="g_c2" label="Unchecked" /><x-ui.checkbox name="g_c3" label="Indeterminate" :indeterminate="true" /><x-ui.checkbox name="g_c4" label="Disabled" :disabled="true" /></div>
                    <x-ui.radio-group name="g_r" label="Radio group" :options="['a' => 'Delivery', 'b' => 'Pickup', 'c' => 'In store']" value="a" />
                    <div class="space-y-1"><x-ui.switch name="g_s1" label="Enabled" help="Helper text" :checked="true" /><x-ui.switch name="g_s2" label="Disabled setting" /><x-ui.switch name="g_s3" label="Locked" :disabled="true" /></div>
                </x-ui.form-section>
                <x-ui.form-section title="Translations and uploads" description="Language switch (red dot = missing translation), uploader, image picker.">
                    <div data-span-all><x-ui.lang-tabs name="g_title" label="Title" :languages="$langs" requiredLocale="en" /></div>
                    <x-ui.uploader name="g_up" hint="PNG or JPG, up to 4 MB" />
                    <div data-span-all><x-ui.image-picker name="g_imgs" :max="6" /></div>
                </x-ui.form-section>
                <x-ui.card title="OtpInput + DateRange" class="max-w-xl"><div class="space-y-5"><x-ui.otp-input name="g_otp" label="Verification code" /><x-ui.otp-input name="g_otp2" :error="'Invalid code'" /><x-ui.date-range :from="now()->subDays(6)->toDateString()" :to="now()->toDateString()" /></div></x-ui.card>
                <x-ui.save-bar cancel="#forms" />
            </form>
        </section>

        {{-- ============ FEEDBACK & OVERLAYS ============ --}}
        <section id="feedback" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="03">Feedback &amp; overlays</x-ui.heading>
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card title="Badge / Chip / Avatar">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2">@foreach (['neutral', 'success', 'warning', 'danger', 'info', 'accent'] as $t)<x-ui.badge :tone="$t" dot>{{ ucfirst($t) }}</x-ui.badge>@endforeach</div>
                        <div class="flex flex-wrap gap-2">@foreach (['neutral', 'success', 'warning', 'danger', 'info', 'accent'] as $t)<x-ui.badge :tone="$t" style="solid" size="sm">{{ ucfirst($t) }}</x-ui.badge>@endforeach</div>
                        <div class="flex flex-wrap gap-2"><x-ui.chip>Filter</x-ui.chip><x-ui.chip :selected="true">Selected</x-ui.chip><x-ui.chip :removable="true">Removable</x-ui.chip></div>
                        <div class="flex flex-wrap items-center gap-3"><x-ui.avatar name="Sara" size="sm" /><x-ui.avatar name="سارة" /><x-ui.avatar name="Lina" size="lg" /><x-ui.avatar-group :items="[['name' => 'A'], ['name' => 'B'], ['name' => 'C'], ['name' => 'D'], ['name' => 'E']]" /></div>
                    </div>
                </x-ui.card>
                <x-ui.card title="Alert">
                    <div class="space-y-2"><x-ui.alert tone="info" title="Heads up">An informational message.</x-ui.alert><x-ui.alert tone="success" :dismissible="true">Saved successfully.</x-ui.alert><x-ui.alert tone="warning">Stock is running low.</x-ui.alert><x-ui.alert tone="danger" title="Could not save">Fix the highlighted fields.</x-ui.alert></div>
                </x-ui.card>
                <x-ui.card title="Progress / Spinner / Skeleton">
                    <div class="space-y-4"><x-ui.progress :value="30" label="30%" /><x-ui.progress :value="70" tone="success" label="70%" /><div class="flex items-center gap-4"><x-ui.progress kind="ring" :value="30" /><x-ui.progress kind="ring" :value="70" tone="success" /><x-ui.progress kind="ring" :value="100" tone="warning" /><x-ui.spinner :size="16" /><x-ui.spinner /><x-ui.spinner :size="40" /></div>
                        <x-ui.skeleton kind="text" /><div class="grid grid-cols-2 gap-3"><x-ui.skeleton kind="stat" /><x-ui.skeleton kind="card" /></div><x-ui.skeleton kind="tablerow" /></div>
                </x-ui.card>
                <x-ui.card title="Toast / Tooltip / Popover / Dropdown / Modal / Drawer">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button variant="secondary" size="sm" onclick="toast({tone:'success',message:'Saved'})">Success toast</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" onclick="toast({tone:'danger',message:'Something failed'})">Error toast</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" onclick="toast({tone:'info',message:'Order archived',action:'Undo',onAction:()=>toast({tone:'success',message:'Restored'})})">Toast + undo</x-ui.button>
                        <x-ui.tooltip text="Hint only"><x-ui.icon-button icon="info" label="Info" variant="outline" /></x-ui.tooltip>
                        <x-ui.popover id="g-pop"><x-slot:trigger class="btn btn-secondary btn-sm">Popover</x-slot:trigger><p class="font-semibold">Archive this order?</p><div class="mt-3 flex gap-2"><x-ui.button size="sm">Archive</x-ui.button><x-ui.button size="sm" variant="ghost">Cancel</x-ui.button></div></x-ui.popover>
                        <x-ui.dropdown id="g-dd"><x-slot:trigger>Actions<x-ui.icon name="chevron-down" class="size-4" /></x-slot:trigger><a href="#" class="ui-menu-item"><x-ui.icon name="edit" class="size-4" />Edit</a><a href="#" class="ui-menu-item"><x-ui.icon name="copy" class="size-4" />Duplicate</a><div class="my-1 border-t border-panel-border"></div><button type="button" class="ui-menu-item danger"><x-ui.icon name="trash" class="size-4" />Delete</button></x-ui.dropdown>
                        <x-ui.button size="sm" data-modal-open="g-modal">Modal / sheet</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" data-modal-open="g-drawer">Drawer</x-ui.button>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">@foreach (['success', 'danger', 'info', 'warning'] as $t)<x-ui.toast :tone="$t" :message="ucfirst($t).' toast'" action="Undo" class="!max-w-none" />@endforeach</div>
                </x-ui.card>
            </div>
            <x-ui.modal id="g-modal" title="Delete order?"><p>This cannot be undone. On a phone this is a bottom sheet.</p><x-slot:footer><x-ui.button variant="secondary" data-modal-close>Cancel</x-ui.button><x-ui.button variant="danger" data-modal-close>Delete</x-ui.button></x-slot:footer></x-ui.modal>
            <x-ui.drawer id="g-drawer" title="Filters"><div class="space-y-4"><x-ui.select name="gd_s" label="Status" :options="['a' => 'All', 'b' => 'Pending']" /><x-ui.date name="gd_d" label="From" /></div><x-slot:footer><x-ui.button class="flex-1" data-modal-close>Apply</x-ui.button></x-slot:footer></x-ui.drawer>
            <div class="grid gap-4 md:grid-cols-2">@foreach (['nodata', 'noresults', 'error', 'forbidden'] as $k)<x-ui.card><x-ui.empty-state :kind="$k" href="#" action="Primary action" /></x-ui.card>@endforeach</div>
        </section>

        {{-- ============ DATA DISPLAY ============ --}}
        <section id="data" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="04">Data display</x-ui.heading>
            <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
                <x-ui.stat-card label="Total sales" value="48,250 ج.م" icon="wallet" trend="up" delta="12.5%" :spark="$spark" hint="vs last week" />
                <x-ui.stat-card label="Orders" value="1,284" icon="cart" tone="violet" trend="down" delta="4.2%" :spark="array_reverse($spark)" hint="vs last week" />
                <x-ui.stat-card label="New customers" value="236" icon="users" tone="mint" trend="flat" delta="0%" />
                <x-ui.stat-card label="Compact" value="375" icon="receipt" tone="sun" size="compact" href="#data" hint="Manage" />
            </div>
            <div class="grid gap-4 xl:grid-cols-3">
                <x-ui.chart-card title="Daily sales" subtitle="Last 30 days" :ranges="['90' => '#', '30' => '#', '7' => '#']" active="30" class="xl:col-span-2">@include('reports._chart', ['chart' => $chart])</x-ui.chart-card>
                <x-ui.chart-card title="Orders by status" subtitle="Donut: a ranked list on mobile">@include('reports._chart', ['chart' => $donut])</x-ui.chart-card>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <x-ui.card variant="hero" title="Hero card" subtitle="Once per page"><p class="text-sm">Soft brand gradient.</p></x-ui.card>
                <x-ui.balance-card amount="1,250.00" label="Available balance" split="Commissions 1,000.00 · Bonuses 250.00" payoutUrl="#" payoutLabel="Request payout" />
                <x-ui.balance-card amount="0.00" label="Available balance (empty)" />
                <x-ui.referral-card code="SARA10" url="https://3alraf.loc/ar?ref=SARA10" />
                <x-ui.suggestion-card :suggestion="['title' => 'Summer bundle for dresses', 'summary' => 'Bundle three bestsellers with a 15% discount.']" status="pending" href="#" action="Review" />
                <x-ui.card title="KeyValue / Stepper / Accordion"><dl class="divide-y divide-panel-border"><x-ui.kv label="Customer" value="أحمد محمود" /><x-ui.kv label="Total" value="1,250 ج.م" kind="money" /><x-ui.kv label="Status"><x-ui.badge tone="success" dot>Paid</x-ui.badge></x-ui.kv></dl><x-ui.stepper class="mt-4" :steps="['Details', 'Review', 'Done']" :current="2" /><x-ui.accordion class="mt-4" title="Advanced (SEO)">Hidden settings.</x-ui.accordion></x-ui.card>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <x-ui.card title="ListItem" :flush="true"><x-ui.list-item title="أحمد محمود" subtitle="#10458 · 1,250 ج.م" href="#"><x-slot:lead><x-ui.avatar name="أ" /></x-slot:lead><x-slot:trail><x-ui.badge tone="warning" dot>Pending</x-ui.badge></x-slot:trail></x-ui.list-item><x-ui.list-item title="Low stock dress" subtitle="3 left"><x-slot:lead><span class="grid size-10 place-items-center rounded-xl bg-panel-surface-sunken"><x-ui.icon name="box" /></span></x-slot:lead></x-ui.list-item></x-ui.card>
                <x-ui.card title="TimelineItem"><ol><x-ui.timeline-item title="Order placed" time="10:20" /><x-ui.timeline-item title="Packed" time="11:05" state="current" /><x-ui.timeline-item title="Delivered" state="upcoming" /></ol></x-ui.card>
                <x-ui.card title="Tabs"><x-ui.tabs :tabs="[['label' => 'All', 'href' => '#', 'count' => 128], ['label' => 'Pending', 'href' => '#', 'count' => 12], ['label' => 'Shipped', 'href' => '#']]" active="All" /><x-ui.tabs class="mt-3" style="pill" :tabs="[['label' => 'All', 'href' => '#', 'count' => 128], ['label' => 'Pending', 'href' => '#', 'count' => 12]]" active="Pending" /></x-ui.card>
            </div>
            <div class="grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-4"><x-ui.order-card :order="$order" href="#" /><x-ui.product-card :product="$product" /><x-ui.product-card :product="$product" size="row" /><x-ui.product-card :product="$product + ['stock' => 0]" size="row" /></div>
            <x-ui.card title="Breadcrumbs / Pagination"><x-ui.breadcrumbs :items="[['Dashboard', '#'], ['Products', '#'], ['Edit', null]]" /><x-ui.pagination class="mt-4" :paginator="new \Illuminate\Pagination\LengthAwarePaginator(range(1, 15), 120, 15, 3, ['path' => '#'])" /></x-ui.card>
        </section>

        {{-- ============ TABLES ============ --}}
        <section id="tables" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="05">Tables</x-ui.heading>
            <x-ui.table-toolbar id="g-filters">
                <x-slot:search><x-ui.search name="g_tq" /></x-slot:search>
                <x-ui.chip :selected="true">All</x-ui.chip><x-ui.chip>Pending</x-ui.chip><x-ui.chip>Shipped</x-ui.chip>
                <x-slot:actions><x-ui.button icon="plus">New</x-ui.button></x-slot:actions>
            </x-ui.table-toolbar>
            <x-ui.bulk-bar class="!static !flex !translate-x-0 !translate-y-0 !inset-auto !min-w-0 w-full" count="3"><x-ui.button size="sm" variant="secondary">Archive</x-ui.button><x-ui.button size="sm" variant="secondary">Export</x-ui.button></x-ui.bulk-bar>
            <x-ui.card title="x-ui.table (server-rendered rows; cards below 768)"><x-ui.table><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th></th></tr></thead><tbody>
                @foreach ([['#10401', 'Customer 1', '117.50'], ['#10402', 'Customer 2', '235.00']] as $r)<tr><td data-label="">{{ $r[0] }}</td><td data-label="Customer">{{ $r[1] }}</td><td data-label="Total">{{ $r[2] }}</td><td class="ui-table-actions"><x-ui.button size="sm" variant="secondary">Open</x-ui.button></td></tr>@endforeach
            </tbody></x-ui.table></x-ui.card>
            <x-ui.card title="Static table (skin of the DataTables output)" :flush="true"><div class="p-4 pt-2"><div class="overflow-x-auto"><table data-static-table data-columns='@json($tblCols)' data-rows='@json($tblRows)' class="display w-full"></table></div></div></x-ui.card>
        </section>

        {{-- ============ SHELL ============ --}}
        <section id="shell" class="scroll-mt-24 space-y-4">
            <x-ui.heading :level="2" eyebrow="06">Shell &amp; navigation</x-ui.heading>
            <x-ui.card title="Shell parts"><p class="text-sm text-panel-text-muted">This page is rendered inside the real shell: sidebar (264 / rail 72 / drawer 300), top bar, bottom nav (below 768), command palette (Ctrl+K) and toast region. Resize the window or switch the panel above to compare.</p>
                <div class="mt-4 max-w-md"><x-ui.page-header title="Page header" :count="128" subtitle="With breadcrumbs, count and actions" :breadcrumbs="[['Home', '#'], ['Products', '#'], ['List', null]]"><x-ui.button variant="secondary">Secondary</x-ui.button><x-ui.button icon="plus">Primary</x-ui.button></x-ui.page-header></div>
            </x-ui.card>
        </section>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var html = document.documentElement;
            function mark(attr, val) { document.querySelectorAll('[' + attr + ']').forEach(function (b) { var on = b.getAttribute(attr) === val; b.setAttribute('aria-pressed', on); b.className = b.className.replace(/border-panel-accent bg-panel-accent-soft text-panel-accent-text|border-panel-border-strong bg-panel-surface text-panel-text hover:border-panel-accent/, on ? 'border-panel-accent bg-panel-accent-soft text-panel-accent-text' : 'border-panel-border-strong bg-panel-surface text-panel-text hover:border-panel-accent'); }); }
            document.addEventListener('click', function (e) {
                var p = e.target.closest('[data-guide-panel]'), t = e.target.closest('[data-guide-theme]');
                if (p) { html.setAttribute('data-panel', p.dataset.guidePanel); mark('data-guide-panel', p.dataset.guidePanel); }
                if (t) { t.dataset.guideTheme === 'dark' ? html.setAttribute('data-theme', 'dark') : html.removeAttribute('data-theme'); mark('data-guide-theme', t.dataset.guideTheme); window.dispatchEvent(new CustomEvent('panel:theme')); }
            });
        });
    </script>
</x-layouts.admin>
