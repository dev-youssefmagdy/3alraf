<?php

namespace App\Http\Requests\Admin;

class AiTrendDismissRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['key' => ['required', 'string', 'size:12', 'alpha_num']];
    }
}
