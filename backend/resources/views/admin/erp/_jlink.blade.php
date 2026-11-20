{{-- Journal entry number: a link only when the viewer may open the entry (QA-010), plain text otherwise. --}}
@can('erp.journals.view')<a class="font-mono text-panel-accent-text" href="{{ route('admin.erp.journals.show', $id) }}">{{ $no }}</a>@else<span class="font-mono">{{ $no }}</span>@endcan
