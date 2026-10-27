<?php

namespace App\Models\Concerns;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Casts\Attribute;

/** phone_full (E.164 for tel:/wa.me) and phone_display (+20 100 848 9183) from the separate code + national columns. */
trait HasStorefrontPhone
{
    protected function phoneColumn(): string
    {
        return 'phone';
    }

    protected function phoneFull(): Attribute
    {
        return Attribute::get(fn () => Phone::e164($this->phone_country_code, $this->{$this->phoneColumn()}));
    }

    protected function phoneDisplay(): Attribute
    {
        return Attribute::get(fn () => Phone::display($this->phone_country_code, $this->{$this->phoneColumn()}));
    }
}
