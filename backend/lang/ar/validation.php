<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'boolean' => 'قيمة :attribute غير صحيحة.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'in' => 'القيمة المحددة في :attribute غير صحيحة.',
    'not_in' => 'القيمة المحددة في :attribute غير مسموحة.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'prohibited' => 'حقل :attribute غير مسموح.',
    'gt' => ['numeric' => 'يجب أن يكون :attribute أكبر من :value.'],
    'min' => ['numeric' => 'يجب ألا يقل :attribute عن :min.', 'string' => 'يجب ألا يقل :attribute عن :min حروف.', 'array' => 'يجب اختيار :min عناصر على الأقل.'],
    'max' => ['numeric' => 'يجب ألا يزيد :attribute عن :max.', 'string' => 'يجب ألا يزيد :attribute عن :max حروف.', 'array' => 'يجب ألا يزيد :attribute عن :max عناصر.'],
    'size' => ['string' => 'يجب أن يتكون :attribute من :size حروف.'],
    'attributes' => [],
];
