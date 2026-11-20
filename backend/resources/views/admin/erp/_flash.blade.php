@if (session('status'))<div class="mb-4 rounded-lg border border-panel-success-solid bg-panel-success-bg px-4 py-2 text-sm text-panel-success-fg">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-lg border border-panel-danger-solid bg-panel-danger-bg px-4 py-2 text-sm text-panel-danger-fg">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
