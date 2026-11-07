@if (session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
