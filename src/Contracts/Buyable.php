<?php

namespace Origami\Cart\Contracts;

interface Buyable
{
    public function getBuyableIdentifier($options = null);
    public function getBuyableDescription($options = null);
    public function getBuyablePrice($options = null);
}
