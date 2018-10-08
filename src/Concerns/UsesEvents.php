<?php

namespace Origami\Cart\Concerns;

use Illuminate\Contracts\Events\Dispatcher;

trait UsesEvents
{

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * Set the event dispatcher on the instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return $this
     */
    public function setEventDispatcher(Dispatcher $events)
    {
        $this->events = $events;
        return $this;
    }

    protected function event($event)
    {
        $this->events->dispatch($event);
    }
}
