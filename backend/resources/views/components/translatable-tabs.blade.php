{{-- Kept for existing pages: the implementation is <x-ui.lang-tabs> (same props). --}}
@props(['name', 'label', 'languages', 'values' => [], 'type' => 'input', 'rows' => 3, 'requiredLocale' => null])
<x-ui.lang-tabs :name="$name" :label="$label" :languages="$languages" :values="$values" :type="$type" :rows="$rows" :requiredLocale="$requiredLocale" />
