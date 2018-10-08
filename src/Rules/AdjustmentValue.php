<?php

namespace Origami\Cart\Rules;

use Money\Money;
use Illuminate\Contracts\Validation\Rule;

class AdjustmentValue implements Rule
{
    public function passes($attribute, $value)
    {
        if ($value instanceof Money) {
            return true;
        }

        return is_int($value) && ($value >= 0);
    }

    public function message()
    {
        return 'The :attribute must be an integer value or Money object.';
    }
}
