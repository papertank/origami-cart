<?php

namespace Origami\Cart\Items;

use Money\Money;
use Money\Currency;
use Origami\Cart\Discount;
use Origami\Cart\Items\Meta;
use Origami\Cart\Items\Options;
use Origami\Cart\Contracts\Buyable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

class Item implements Arrayable, Jsonable
{
    public $rowId;
    public $id;
    public $qty;
    public $name;
    public $price;
    public $options;
    public $meta;
    public $modelType = null;
    public $modelId = null;
    public $taxRate = 0;
    public $discountRate;
    
    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param int|Money      $price
     * @param array      $options
     */
    public function __construct($id, $name, $price, array $options = [], array $meta = [])
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if (! $price instanceof Money && ! is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }

        $this->id       = $id;
        $this->name     = $name;
        $this->price    = $this->asMoney($price);
        $this->options  = new Options($options);
        $this->meta = new Meta($meta);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @return string
     */
    public function price()
    {
        return $this->price;
    }
    
    /**
     * Returns the formatted price with TAX.
     *
     * @return string
     */
    public function priceTax()
    {
        return $this->price()->add($this->tax());
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->price()->multiply($this->qty);
    }
    
    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @return string
     */
    public function total()
    {
        return $this->priceTax()->multiply($this->qty);
    }

    /**
     * Returns the formatted tax.
     *
     * @return string
     */
    public function tax()
    {
        return $this->price()->multiply($this->taxRate / 100);
    }
    
    /**
     * Returns the formatted tax.
     *
     * @return string
     */
    public function taxTotal()
    {
        return $this->tax()->multiply($this->qty);
    }

    public function hasDiscount()
    {
        return ! is_null($this->discountRate);
    }

    public function priceDiscount()
    {
        return $this->discountedPrice();
    }

    public function discountedPrice()
    {
        return $this->discountRate ? $this->discountRate->applyDiscount($this->price) : $this->price;
    }

    public function discount()
    {
        return $this->discountRate ? $this->discountRate->calculateDiscount($this->price) : $this->price->multiply(0);
    }

    public function discountTotal()
    {
        return $this->discount()->multiply($this->qty);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty) || ! is_numeric($qty)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        $this->qty = $qty;
    }

    /**
     * Set the discount.
     *
     * @param array $attributes
     * @return \Origami\Cart\Items\Item
     */
    public function setDiscount(array $attributes)
    {
        if (!isset($attributes[0])) {
            throw new \InvalidArgumentException('Please supply a valid discount attributes.');
        }
        $this->discountRate = new Discount($attributes[0], isset($attributes[1]) ? $attributes[1] : null, isset($attributes[2]) ? $attributes[2] : null);
        return $this;
    }

    public function clearDiscount()
    {
        $this->discountRate = null;
        return $this;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param \Origami\Cart\Contracts\Buyable $item
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id       = $item->getBuyableIdentifier($this->options);
        $this->name     = $item->getBuyableDescription($this->options);
        $this->price    = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id       = array_get($attributes, 'id', $this->id);
        $this->qty      = array_get($attributes, 'qty', $this->qty);
        $this->name     = array_get($attributes, 'name', $this->name);
        $this->price    = array_get($attributes, 'price', $this->price);
        $this->options  = new Option(array_get($attributes, 'options', $this->options));
        $this->options = new Meta(array_get($attributes, 'meta', $this->meta));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     * @return \Origami\Cart\Items\Item
     */
    public function associate($model)
    {
        if (is_object($model)) {
            $this->modelType = get_class($model);
            $this->modelId = $model->id ?: $this->id;
        } else {
            $this->modelType = $model;
            $this->modelId = $this->id;
        }
        
        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int|float $taxRate
     * @return \Origami\Cart\Items\Item
     */
    public function setTaxRate($taxRate)
    {
        $this->taxRate = $taxRate;
        
        return $this;
    }

    /**
     * Set the meta for this item.
     *
     * @param array $meta
     */
    public function setMeta($meta)
    {
        $this->meta = new Meta($meta);
    }

    public function hasModel($model)
    {
        return ($this->modelType == get_class($model)) && ($this->modelId == $model->id);
    }

    public function hasCurrency(Currency $currency)
    {
        return $this->getCurrency()->equals($currency);
    }

    public function getCurrency()
    {
        return $this->price->getCurrency();
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

        if (in_array($attribute, ['priceTax','subtotal','total','tax','taxTotal','priceDiscount','discountedPrice','discount','discountTotal'])) {
            return $this->{$attribute}();
        }
        
        if ($attribute === 'model' && isset($this->modelType) && !empty($this->modelId)) {
            return with(new $this->modelType)->find($this->modelId);
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \Origami\Cart\Contracts\Buyable $item
     * @param array                                      $options
     * @return \Origami\Cart\Items\Item
     */
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), $item->getBuyablePrice($options), $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \Origami\Cart\Items\Item
     */
    public static function fromArray(array $attributes)
    {
        $options = array_get($attributes, 'options', []);
        $meta = array_get($attributes, 'meta', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $options, $meta);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     * @return \Origami\Cart\Items\Item
     */
    public static function fromAttributes($id, $name, $price, array $options = [], array $meta = [])
    {
        return new self($id, $name, $price, $options, $meta);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price->getAmount(),
            'options'  => $this->options->toArray(),
            'meta' => $this->meta->toArray(),
            'taxRate' => $this->taxRate,
            'tax'      => $this->tax->getAmount(),
            'subtotal' => $this->subtotal->getAmount(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function asMoney($amount)
    {
        if ($amount instanceof Money) {
            return $amount;
        }

        if (is_null($amount)) {
            return null;
        }

        return new Money((int) $amount, new Currency(config('cart.default_currency')));
    }
}
