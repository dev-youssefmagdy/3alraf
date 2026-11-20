{{-- Date input (Figma DatePicker, single): native date control, LTR digits. For a range use <x-ui.date-range>. --}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'error' => null])
<x-ui.input :name="$name" :label="$label" type="date" :value="$value" :required="$required" :help="$help" :error="$error" dir="ltr" {{ $attributes }} />
