<?php

namespace IPE\Service\Container;

use IPE\Service\Container;

/**
 * A modified version of illuminate/container v5.2.45.
 *
 * @original-author Taylor Otwell
 * @license MIT
 * @see https://github.com/laravel/framework/blob/v5.2.45/LICENSE.txt
 */
class ContextualBindingBuilder
{
    /**
     * The underlying container instance.
     *
     * @var \IPE\Service\Container
     */
    protected $container;

    /**
     * The concrete instance.
     *
     * @var string
     */
    protected $concrete;

    /**
     * The abstract target.
     *
     * @var string
     */
    protected $needs;

    /**
     * Create a new contextual binding builder.
     *
     * @param string $concrete
     * @return void
     */
    public function __construct(Container $container, $concrete)
    {
        $this->concrete = $concrete;
        $this->container = $container;
    }

    /**
     * Define the abstract target that depends on the context.
     *
     * @param string $abstract
     * @return $this
     */
    public function needs($abstract)
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     *
     * @param \Closure|string $implementation
     * @return void
     */
    public function give($implementation)
    {
        $this->container->addContextualBinding($this->concrete, $this->needs, $implementation);
    }
}
