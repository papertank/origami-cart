<?php

namespace Origami\Cart\Adjustments;

use Money\Money;
use Money\Currency;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Origami\Cart\Rules\AdjustmentValue;
use Illuminate\Support\Facades\Validator;
use Origami\Cart\Exceptions\InvalidAdjustmentException;

class Adjustment
{
    protected $attributes = [];

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
        $this->validate($this->attributes);
    }

    public function apply(Money $price)
    {
        return $price->add($this->calculate($price));
    }

    public function isDiscount()
    {
        return $this->attributes['type'] == 'discount';
    }

    public function calculate(Money $price)
    {
        if ($this->isPercentage()) {
            $value = $price->multiply($this->percentage / 100);
        } else {
            $value = $this->asMoney($this->value, $price->getCurrency());
        }

        if (! $this->isDiscount()) {
            return $value;
        }

        if ($value->greaterThan($price)) {
            return $value->multiply(0);
        }

        return $value->multiply(-1);
    }

    public function isPercentage()
    {
        return isset($this->attributes['percentage']);
    }

    public function getOrder()
    {
        if ($order = array_get($this->attributes, 'order')) {
            return $order;
        }

        return array_get([
            'discount' => 1,
            'other' => 2,
            'delivery' => 3,
        ], $this->type, 2);
    }

    protected function asMoney($value, Currency $currency)
    {
        if ($value instanceof Money) {
            return $value;
        }

        return new Money($value, $currency);
    }

    protected function validate(array $attributes)
    {
        $validator = Validator::make($attributes, static::rules());

        if ($validator->fails()) {
            throw new InvalidAdjustmentException('Invalid adjustment', $validator->errors());
        }
    }

    protected static function rules()
    {
        return [
            'name' => ['required'],
            'type' => ['required', 'in:discount,other'],
            'percentage' => ['required_without:value', 'numeric', 'min:0', 'max:100'],
            'value' => ['required_without:percentage', new AdjustmentValue],
        ];
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($key)
    {
        $method = 'get'.Str::studly($key);

        if (method_exists($this, $method)) {
            $this->{$method}();
        }

        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return null;
    }
}
