<?php

namespace Origami\Cart;

use Money\Money;
use InvalidArgumentException;

class Discount
{
    /**
     * @var int|float
     */
    private $value;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $description;

    /**
     *
     * @param int|float $value
     * @param string $type
     * @param string $description
     */
    public function __construct($value, $type = 'currency', $description = '')
    {
        if ($type == 'percentage' && ($value < 0 || $value > 100)) {
            throw new InvalidArgumentException('Please supply a valid discount value.');
        }

        if ($type != 'currency' && $type != 'percentage') {
            throw new InvalidArgumentException('Please supply a valid discount type.');
        }

        $this->value = $value instanceof Money ? $value->getAmount() : $value;
        $this->type = $type;
        $this->description = $description;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'symbol') {
            switch ($this->type) {
                case 'currency':
                    return '-';
                    break;
                case 'percentage':
                    return '%';
                    break;
            }
        }

        return null;
    }

    public function applyDiscount($price)
    {
        return $price->subtract($this->calculateDiscount($price));
    }

    public function calculateDiscount($price)
    {
        switch ($this->type) {
            case 'currency':
                $value = new Money($this->value, $price->getCurrency());
                return ($value->greaterThan($price)) ? $value->multiply(0) : $value;
                break;
            case 'percentage':
                return $price->multiply($this->value / 100);
                break;
        }
    }
}
