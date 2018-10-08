<?php

namespace Origami\Cart\Concerns;

use Money\Currency;
use RuntimeException;
use Illuminate\Session\SessionManager;
use Origami\Cart\Items\ItemCollection;
use Origami\Cart\Adjustments\AdjustmentCollection;

trait UsesSession
{

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    protected $session;

    public function usesSession()
    {
        return true;
    }

    public function load()
    {
        $state = $this->session->get($this->getSessionKey());
        $this->items = isset($state['items']) ? $state['items'] : new ItemCollection;
        $this->adjustments = isset($state['adjustments']) ? $state['adjustments'] : new AdjustmentCollection;
        if (isset($state['currency'])) {
            $this->setCurrency(new Currency($state['currency']));
        }
        $this->loaded = true;
        return $this;
    }

    public function save()
    {
        if (! $this->loaded) {
            throw new RuntimeException('Cart saved before loading');
        }

        $this->session->put($this->getSessionKey(), [
            'items' => $this->items,
            'adjustments' => $this->adjustments,
            'currency' => $this->getCurrency()->getCode(),
        ]);
        return $this;
    }

    public function destroy()
    {
        $this->session->remove($this->getSessionKey());
        $this->reload();
    }

    protected function getSessionKey()
    {
        return 'cart-'.$this->name;
    }

    public function jsonSerialize()
    {
        return [
            'items' => $this->items,
            'adjustments' => $this->adjustments,
            'currency' => $this->getCurrency()->getCode(),
        ];
    }

    /**
     * Set the session on the instance.
     *
     * @param  \Illuminate\Session\SessionManager  $session
     * @return $this
     */
    public function setSession(SessionManager $session)
    {
        $this->session = $session;

        return $this;
    }
}
