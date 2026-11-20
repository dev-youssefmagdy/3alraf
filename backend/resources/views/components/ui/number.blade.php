{{-- Number input (Figma Field › number): LTR digits inside RTL, decimal keypad on phones. Props as <x-ui.input>. --}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'error' => null, 'step' => 'any', 'suffix' => null])
<x-ui.input :name="$name" :label="$label" type="number" :value="$value" :required="$required" :help="$help" :error="$error" :suffix="$suffix" step="{{ $step }}" inputmode="decimal" dir="ltr" {{ $attributes }} />
