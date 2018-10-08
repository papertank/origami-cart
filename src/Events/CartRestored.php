<?php

namespace Origami\Cart\Events;

use Origami\Cart\Items\Item;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CartRestored
{
    use Dispatchable, SerializesModels;
}
