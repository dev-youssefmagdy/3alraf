{{-- Image picker (Figma Uploader › image grid): multi-upload grid, drag to add, remove with undo, first image = main. Same props as <x-ui.uploader>. --}}
@props(['name', 'max' => 12, 'existing' => [], 'removeName' => null, 'hint' => null])
<x-ui.uploader :name="$name" :multiple="true" :max="$max" :existing="$existing" :removeName="$removeName" :hint="$hint ?? __('ui.first_is_main')" {{ $attributes }} />
