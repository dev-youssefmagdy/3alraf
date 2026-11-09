<div><label class="form-label" for="group_by">{{ __('marketing.an.group_by') }}</label>
    <select id="group_by" name="group_by" class="form-control">
        @foreach (['day' => 'by_day', 'week' => 'by_week', 'month' => 'by_month'] as $v => $k)
            <option value="{{ $v }}" @selected($by === $v)>{{ __('marketing.an.'.$k) }}</option>
        @endforeach
    </select></div>
