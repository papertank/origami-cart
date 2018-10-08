<?php

namespace Origami\Cart\Events;

use Origami\Cart\Items\Item;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CartItemAdded
{
    use Dispatchable, SerializesModels;

    protected $item;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Item $item)
    {
        $this->item = $item;
    }
}
