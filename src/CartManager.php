<?php

namespace Origami\Cart;

use Illuminate\Support\Arr;
use Origami\Cart\Contracts\Cart as CartContract;

class CartManager
{
    /**
     * The active cart instances
     */
    protected $instances = [];

    /**
     * Create a new database manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }
    
    public function instance($name = null)
    {
        $name = $name ?: $this->getDefaultInstance();

        if (! isset($this->instances[$name])) {
            $this->instances[$name] = $this->configure(
                $this->makeInstance($name)
            );
        }

        return $this->instances[$name];
    }

    /**
     * Make the cart instance.
     *
     * @param  string  $name
     * @return \Origami\Cart\Contracts\Cart
     */
    protected function makeInstance($name)
    {
        $config = $this->configuration($name);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $name, $config);
        }

        return new Cart($name, $config);
    }

    /**
     * Get the configuration for an instance.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function configuration($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        $instances = $this->app['config']['cart.instances'];

        if (is_null($config = Arr::get($instances, $name))) {
            throw new InvalidArgumentException("Instance [{$name}] not configured.");
        }

        return $config;
    }

    /**
     * Prepare the cart instance.
     *
     * @param  \Origami\Cart\Contracts\Cart  $instance
     * @return \Illuminate\Database\Connection
     */
    protected function configure(CartContract $instance)
    {
        if ($this->app->bound('events')) {
            $instance->setEventDispatcher($this->app['events']);
        }

        if ($instance->usesSession() && $this->app->bound('session')) {
            $instance->setSession($this->app['session']);
        }

        if ($instance->usesDatabase() && $this->app->bound('db')) {
            $instance->setDatabaseManager($this->app['db']);
        }

        return $instance;
    }

    public function getInstances()
    {
        return $this->instances;
    }

    public function getDefaultInstance()
    {
        return $this->app['config']['cart.default'];
    }

    public function setDefaultInstance($name)
    {
        $this->app['config']['cart.default'] = $name;
    }

    /**
     * Dynamically pass methods to the default instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->instance()->$method(...$parameters);
    }
}
