{{-- Kept for existing pages: the implementation moved to <x-ui.uploader> (same props). --}}
@props(['name', 'multiple' => false, 'max' => null, 'existing' => [], 'removeName' => null, 'preview' => null, 'hint' => null])
<x-ui.uploader :name="$name" :multiple="$multiple" :max="$max" :existing="$existing" :removeName="$removeName" :preview="$preview" :hint="$hint" {{ $attributes }} />
