<?php

namespace Origami\Cart\Items;

use Illuminate\Support\Collection;

class Meta extends Collection
{
    /**
     * Get the meta by the given key.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
