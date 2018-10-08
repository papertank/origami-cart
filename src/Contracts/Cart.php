<?php

namespace Origami\Cart\Contracts;

interface Cart
{
    public function load();
    public function reload();
    public function save();
    public function usesDatabase();
    public function usesSession();
}
