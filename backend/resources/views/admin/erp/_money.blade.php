{{-- usage: @include('admin.erp._money', ['v' => '12.5', 'neg' => true]) --}}
<span class="tabular-nums {{ ! empty($neg) && (float) $v < 0 ? 'text-red-600' : '' }}">{{ number_format((float) $v, 2) }}</span>
