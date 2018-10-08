<?php

namespace Origami\Cart;

use Closure;
use Money\Money;
use Money\Currency;
use Origami\Cart\Items\Item;
use Origami\Cart\Contracts\Buyable;
use Origami\Cart\Events\CartItemAdded;
use Origami\Cart\Items\ItemCollection;
use Origami\Cart\Adjustments\Adjustment;
use Origami\Cart\Events\CartItemDeleted;
use Origami\Cart\Events\CartItemUpdated;
use Origami\Cart\Adjustments\AdjustmentCollection;
use Origami\Cart\Exceptions\InvalidCurrencyException;
use Origami\Cart\Exceptions\CartItemNotFoundException;

class Cart implements Contracts\Cart
{
    use Concerns\UsesDatabase,
        Concerns\UsesSession,
        Concerns\UsesEvents;

    /**
     * Holds the name of the instance.
     *
     * @var string
     */
    protected $name;

    /**
     * Cart items
     *
     * @var ItemCollection
     */
    protected $items;

    /**
     * Cart adjustments
     *
     * @var AdjustmentCollection
     */
    protected $adjustments;

    /**
     * Currency
     *
     * @var Currency
     */
    protected $currency;

    protected $loaded = false;

    public function __construct($name, array $config = [])
    {
        $this->name = $name;
        $this->config = $config;
        
        $this->items = new ItemCollection;
        $this->adjustments = new AdjustmentCollection;
        $this->setCurrency(new Currency($config['currency']));
    }
    
    /**
     * Add an item to the cart.
     *
     * @param mixed     $id
     * @param string     $name
     * @param int       $qty
     * @param int|Money     $price
     * @param array     $options
     * @param array     $meta
     * @return \Origami\Cart\Items\Item
     */
    public function add($id, $name, $qty = null, $price = null, $options = [], $meta = [])
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $item = $this->createItem($id, $name, $qty, $price, $options, $meta);

        if ($this->items()->has($item->rowId)) {
            $item->qty += $this->items()->get($item->rowId)->qty;
        }

        $this->items()->put($item->rowId, $item);

        $this->event(new CartItemAdded($item));

        $this->save();

        return $item;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $value
     * @return \Origami\Cart\Items\Item
     */
    public function update($rowId, $value)
    {
        $item = $this->get($rowId);
        $rowId = $item->rowId;

        if ($value instanceof Closure) {
            $item = $value($item);
        } elseif ($value instanceof Buyable) {
            $item->updateFromBuyable($value);
        } elseif (is_array($value)) {
            $item->updateFromArray($value);
        } else {
            $item->qty = $value;
        }

        if ($rowId !== $item->rowId) {
            $this->items()->pull($rowId);
            if ($this->items()->has($item->rowId)) {
                $existingItem = $this->get($item->rowId);
                $item->setQuantity($existingItem->qty + $item->qty);
            }
        }

        if ($item->qty <= 0) {
            $this->remove($item->rowId);
            return;
        } else {
            $this->items()->put($item->rowId, $item);
        }

        $this->event(new CartItemUpdated($item));
        $this->save();

        return $item;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function remove($rowId)
    {
        $item = $this->get($rowId);

        $this->items()->pull($item->rowId);

        $this->event(new CartItemDeleted($item));

        $this->save();
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     * @return \Origami\Cart\Items\Item
     */
    public function get($rowId)
    {
        if ($rowId instanceof Item) {
            $rowId = $rowId->rowId;
        }

        if (!$this->items()->has($rowId)) {
            throw new CartItemNotFoundException("The cart does not contain rowId {$rowId}.");
        }

        return $this->items()->get($rowId);
    }

    public function adjustments()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->adjustments->sortBy(function ($adjustment) {
            return $adjustment->order;
        });
    }

    /**
     * Load and the content of the cart.
     *
     * @return \Origami\Cart\Items\ItemCollection
     */
    public function content()
    {
        if (! $this->loaded) {
            $this->load();
        }

        return $this->items;
    }

    public function items()
    {
        return $this->content();
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        return $this->items()->sum('qty');
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @return Money
     */
    public function total()
    {
        $total = $this->items()->reduce(function ($total, Item $item) {
            return $total->add($item->total());
        }, new Money(0, $this->currency));

        return $total;
    }

    public function grandTotal()
    {
        $total = $this->total();

        foreach ($this->adjustments() as $adjustment) {
            $total = $adjustment->apply($total);
        }

        return $total;
    }

    public function adjustmentsCount($type)
    {
        return $this->adjustments()->count(function ($adjustment) use ($type) {
            return $adjustment->type == $type;
        });
    }

    public function adjustmentsTotal($type)
    {
        $adjustments = $this->adjustments()->filter(function ($adjustment) use ($type) {
            return $adjustment->type == $type;
        });

        $cartTotal = $this->total();

        $total = $adjustments->reduce(function ($total, Adjustment $adjustment) use ($cartTotal) {
            return $total->add($adjustment->calculate($cartTotal));
        }, new Money(0, $this->currency));

        return $total;
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @return Money
     */
    public function tax()
    {
        $tax = $this->items()->reduce(function ($tax, Item $item) {
            return $tax->add($item->tax());
        }, new Money(0, $this->currency));

        return $tax;
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return Money
     */
    public function subtotal()
    {
        $subTotal = $this->items()->reduce(function ($subTotal, Item $item) {
            return $subTotal->add($item->subtotal());
        }, new Money(0, $this->currency));

        return $subTotal;
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Origami\Cart\Items\ItemCollection
     */
    public function search(Closure $search)
    {
        return $this->items()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     * @return void
     */
    public function associate($rowId, $model)
    {
        if (is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $item = $this->get($rowId);

        $item->associate($model);

        $this->items()->put($item->rowId, $item);

        $this->save();
    }

    public function addAdjustment(Adjustment $adjustment)
    {
        $adjustments = $this->adjustments();

        $adjustments->push($adjustment);
        $this->adjustments = $adjustments;

        $this->save();

        return $adjustment;
    }

    public function removeAdjustmentWithName($name)
    {
        $this->adjustments = $this->adjustments()->reject(function ($adjustment) use ($name) {
            return $adjustment->name == $name;
        });

        $this->save();
    }

    public function removeAdjustmentsWithType($type)
    {
        $this->adjustments = $this->adjustments()->reject(function ($adjustment) use ($type) {
            return $adjustment->type == $type;
        });

        $this->save();
    }

    public function clearAdjustments()
    {
        $this->adjustments = new AdjustmentCollection;
        
        $this->save();
    }

    public function setCurrency(Currency $currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function reload()
    {
        $this->loaded = false;
        return $this->load();
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        if ($attribute === 'total') {
            return $this->total();
        }

        if ($attribute === 'discount') {
            return $this->total();
        }

        if ($attribute === 'tax') {
            return $this->tax();
        }

        if ($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     * @param array     $meta
     * @return \Origami\Cart\Items\Item
     */
    protected function createItem($id, $name, $qty, $price, array $options, array $meta)
    {
        if ($id instanceof Buyable) {
            $item = Item::fromBuyable($id, $qty ? : []);
            $item->setQuantity($name ? : 1);
            $item->associate($id);
            if ($price) {
                $item->setMeta($price);
            }
        } elseif (is_array($id)) {
            $item = Item::fromArray($id);
            $item->setQuantity($id['qty']);
        } else {
            $item = Item::fromAttributes($id, $name, $price, $options, $meta);
            $item->setQuantity($qty);
        }

        $item->setTaxRate(config('cart.tax'));

        if (!$item->hasCurrency($this->currency)) {
            throw new InvalidCurrencyException('You cannot add ' . $item->getCurrency()->getCode() . ' currency to this cart instance');
        }

        return $item;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     * @return bool
     */
    protected function isMulti($item)
    {
        if (!is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }
}
