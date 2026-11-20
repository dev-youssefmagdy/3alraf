{{-- usage: @include('admin.erp._money', ['v' => '12.5', 'neg' => true]) --}}
<span class="tabular-nums {{ ! empty($neg) && (float) $v < 0 ? 'text-panel-danger-fg' : '' }}">{{ number_format((float) $v, 2) }}</span>
