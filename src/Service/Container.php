<?php

namespace IPE\Service;

use IPE\Exception;

/**
 * A modified version of illuminate/container v5.2.45.
 *
 * @original-author Taylor Otwell
 * @license MIT
 * @see https://github.com/laravel/framework/blob/v5.2.45/LICENSE.txt
 */
class Container
{
    /**
     * An array of the types that have been resolved.
     *
     * @var array<string, bool>
     */
    protected $resolved = [];

    /**
     * The container's bindings.
     *
     * @var array<string, array{concrete: \Closure, shared: bool}>
     */
    protected $bindings = [];

    /**
     * The container's shared instances.
     *
     * @var array<string, mixed>
     */
    protected $instances = [];

    /**
     * The registered type aliases.
     *
     * @var array<string, string>
     */
    protected $aliases = [];

    /**
     * The extension closures for services.
     *
     * @var array<string, \Closure[]>
     */
    protected $extenders = [];

    /**
     * All of the registered tags.
     *
     * @var array<string, string[]>
     */
    protected $tags = [];

    /**
     * The stack of concretions currently being built.
     *
     * @var string[]
     */
    protected $buildStack = [];

    /**
     * The contextual binding map.
     *
     * @var array<string, array<string, \Closure|string>>
     */
    public $contextual = [];

    /**
     * All of the registered rebound callbacks.
     *
     * @var array<string, \Closure[]>
     */
    protected $reboundCallbacks = [];

    /**
     * All of the global resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $globalResolvingCallbacks = [];

    /**
     * All of the global after resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $globalAfterResolvingCallbacks = [];

    /**
     * All of the resolving callbacks by class type.
     *
     * @var array<string, \Closure[]>
     */
    protected $resolvingCallbacks = [];

    /**
     * All of the after resolving callbacks by class type.
     *
     * @var array<string, \Closure[]>
     */
    protected $afterResolvingCallbacks = [];

    /**
     * Define a contextual binding.
     *
     * @param string $concrete
     * @return \IPE\Service\Container\ContextualBindingBuilder
     */
    public function when($concrete)
    {
        $concrete = $this->normalize($concrete);

        return new Container\ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        $abstract = $this->normalize($abstract);

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved($abstract)
    {
        $abstract = $this->normalize($abstract);

        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param string $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$this->normalize($name)]);
    }

    /**
     * Register a binding with the container.
     *
     * @param string|array<string, string> $abstract the abstract type, or [abstract => alias]
     * @param \Closure|string|null $concrete
     * @param bool $shared
     *
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $abstract = $this->normalize($abstract);

        $concrete = $this->normalize($concrete);

        // If the given types are actually an array, we will assume an alias is being
        // defined and will grab this "real" abstract class name and register this
        // alias with the container so that it can be used as a shortcut for it.
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        // If no concrete type was given, we will simply set the concrete type to the
        // abstract type. After that, the concrete type to be registered as shared
        // without being forced to state their classes in both of the parameters.
        $this->dropStaleInstances($abstract);

        if ($concrete === null) {
            $concrete = $abstract;
        }

        // If the factory is not a Closure, it means it is just a class name which is
        // bound into this container to the abstract type and we will just wrap it
        // up inside its own Closure to give us more convenience when extending.
        if (!$concrete instanceof \Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        // If the abstract type was already resolved in this container we'll fire the
        // rebound listener so that any objects which have already gotten resolved
        // can have their copy of the object updated via the listener callbacks.
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Get the Closure to be used when building a type.
     *
     * @param string $abstract
     * @param string $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return static function (Container $c, array $parameters = []) use ($abstract, $concrete) {
            $method = ($abstract === $concrete) ? 'build' : 'make';

            return $c->{$method}($concrete, $parameters);
        };
    }

    /**
     * Add a contextual binding to the container.
     *
     * @param string $concrete
     * @param string $abstract
     * @param \Closure|string $implementation
     * @return void
     */
    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        $this->contextual[$this->normalize($concrete)][$this->normalize($abstract)] = $this->normalize($implementation);
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @param bool $shared
     *
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string|array<string, string> $abstract the abstract type, or [abstract => alias]
     * @param \Closure|string|null $concrete
     *
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Wrap a Closure such that it is shared.
     *
     * @return \Closure
     */
    public function share(\Closure $closure)
    {
        return static function ($container) use ($closure) {
            // We'll simply declare a static variable within the Closures and if it has
            // not been set we will execute the given Closures to resolve this value
            // and return it back to these consumers of the method as an instance.
            static $object;

            if ($object === null) {
                $object = $closure($container);
            }

            return $object;
        };
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param string $abstract
     *
     * @return void
     */
    public function extend($abstract, \Closure $closure)
    {
        $abstract = $this->normalize($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;
        }
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string|array<string, string> $abstract the abstract type, or [abstract => alias]
     * @param mixed $instance
     *
     * @return void
     */
    public function instance($abstract, $instance)
    {
        $abstract = $this->normalize($abstract);

        // First, we will extract the alias from the abstract if it is an array so we
        // are using the correct name when binding the type. If we get an alias it
        // will be registered with the container so we can resolve it out later.
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        unset($this->aliases[$abstract]);

        // We'll check to determine if this type has been bound before, and if it has
        // we will fire the rebound callbacks registered with the container and it
        // can be updated with consuming classes that have gotten resolved here.
        $bound = $this->bound($abstract);

        $this->instances[$abstract] = $instance;

        if ($bound) {
            $this->rebound($abstract);
        }
    }

    /**
     * Assign a set of tags to a given binding.
     *
     * @param string[]|string $abstracts
     * @param string[]|string $tags
     * @return void
     */
    public function tag($abstracts, $tags)
    {
        foreach ((array) $tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $this->normalize($abstract);
            }
        }
    }

    /**
     * Resolve all of the bindings for a given tag.
     *
     * @param string $tag
     *
     * @return list<mixed>
     */
    public function tagged($tag)
    {
        $results = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $abstract) {
                $results[] = $this->make($abstract);
            }
        }

        return $results;
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $this->normalize($abstract);
    }

    /**
     * Extract the type and alias from a given definition.
     *
     * @param array<string, string> $definition [abstract => alias]
     * @return array{string, string}
     */
    protected function extractAlias(array $definition)
    {
        $abstract = key($definition);
        $alias = current($definition);
        if ($abstract === null || $alias === false) {
            throw new Exception\InvalidArgument('Invalid [abstract => alias] definition');
        }

        return [$abstract, $alias];
    }

    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param string $abstract
     *
     * @return mixed the resolved instance if the abstract is already bound, null otherwise
     */
    public function rebinding($abstract, \Closure $callback)
    {
        $this->reboundCallbacks[$this->normalize($abstract)][] = $callback;

        if ($this->bound($abstract)) {
            return $this->make($abstract);
        }

        return null;
    }

    /**
     * Refresh an instance on the given target and method.
     *
     * @param string $abstract
     * @param object $target
     * @param string $method
     *
     * @return mixed the resolved instance if the abstract is already bound, null otherwise
     */
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($this->normalize($abstract), static function ($app, $instance) use ($target, $method) {
            $target->{$method}($instance);
        });
    }

    /**
     * Fire the "rebound" callbacks for the given abstract type.
     *
     * @param string $abstract
     *
     * @return void
     */
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    /**
     * Get the rebound callbacks for a given type.
     *
     * @param string $abstract
     * @return \Closure[]
     */
    protected function getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }

        return [];
    }

    /**
     * Wrap the given closure such that its dependencies will be injected when executed.
     *
     * @param array<string|int, mixed> $parameters
     * @return \Closure
     */
    public function wrap(\Closure $callback, array $parameters = [])
    {
        return function () use ($callback, $parameters) {
            return $this->call($callback, $parameters);
        };
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param callable|array{object, string}|string $callback a callable, or a string in the Class@method syntax
     * @param array<string|int, mixed> $parameters
     * @param string|null $defaultMethod
     *
     * @throws \IPE\Exception\InvalidArgument\InContainer
     *
     * @return mixed
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        if (is_string($callback) && (strpos($callback, '@') !== false || $defaultMethod !== null)) {
            return $this->callClass($callback, $parameters, $defaultMethod);
        }
        if (!is_callable($callback)) {
            throw new Exception\InvalidArgument\InContainer('The provided callback is not callable');
        }

        $dependencies = $this->getMethodDependencies($callback, $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param mixed $callback
     * @return bool
     */
    protected function isCallableWithAtSign($callback)
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param callable|array{object|string, string}|string $callback
     * @param array<string|int, mixed> $parameters
     *
     * @return array<string|int, mixed>
     */
    protected function getMethodDependencies($callback, array $parameters = [])
    {
        $dependencies = [];

        foreach ($this->getCallReflector($callback)->getParameters() as $parameter) {
            $this->addDependencyForCallParameter($parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param callable|array{object|string, string}|string $callback
     *
     * @return \ReflectionFunctionAbstract
     */
    protected function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            list($class, $method) = explode('::', $callback, 2);

            return new \ReflectionMethod($class, $method);
        }
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }
        if ($callback instanceof \Closure || is_string($callback)) {
            return new \ReflectionFunction($callback);
        }
        if (is_object($callback)) {
            return new \ReflectionMethod($callback, '__invoke');
        }

        throw new Exception\InvalidArgument\InContainer('Unsupported callback');
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param array<string|int, mixed> $parameters
     * @param list<mixed> $dependencies
     *
     * @return void
     */
    protected function addDependencyForCallParameter(\ReflectionParameter $parameter, array &$parameters, &$dependencies)
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif (($className = $this->getParameterClassName($parameter)) !== '') {
            $dependencies[] = $this->make($className);
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param string $target
     * @param array<string|int, mixed> $parameters
     * @param string|null $defaultMethod
     *
     * @throws \IPE\Exception\InvalidArgument\InContainer
     *
     * @return mixed
     */
    protected function callClass($target, array $parameters = [], $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // If the listener has an @ sign, we will assume it is being used to delimit
        // the class name from the handle method name. This allows for handlers
        // to run multiple handler methods in a single class for convenience.
        $method = count($segments) === 2 ? $segments[1] : $defaultMethod;

        if ($method === null) {
            throw new Exception\InvalidArgument\InContainer('Method not provided.');
        }
        $instance = $this->make($segments[0]);
        if (!is_object($instance)) {
            throw new Exception\InvalidArgument\InContainer("Target [{$segments[0]}] is not an object.");
        }

        return $this->call([$instance, $method], $parameters);
    }

    /**
     * Resolve the given type from the container.
     *
     * @template T of object
     *
     * @param class-string<T>|string $abstract
     * @param array<string|int, mixed> $parameters
     *
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($this->normalize($abstract));

        // If an instance of the type is currently being managed as a singleton we'll
        // just return an existing instance instead of instantiating new instances
        // so the developer can keep using the same objects instance every time.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // We're ready to instantiate an instance of the concrete type registered for
        // the binding. This will instantiate the types, as well as resolve any of
        // its "nested" dependencies recursively until all have gotten resolved.
        if ($concrete instanceof \Closure || $concrete === $abstract) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        // If we defined any extenders for this type, we'll need to spin through them
        // and apply them to the object being built. This allows for the extension
        // of services, such as changing configuration or decorating the object.
        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        // If the requested type is registered as a singleton we'll want to cache off
        // the instances in "memory" so we can return it later without creating an
        // entirely new instance of an object on each subsequent request for it.
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->fireResolvingCallbacks($abstract, $object);

        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param string $abstract
     * @return \Closure|string
     */
    protected function getConcrete($abstract)
    {
        if (null !== ($concrete = $this->getContextualConcrete($abstract))) {
            return $concrete;
        }

        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Get the contextual concrete binding for the given abstract.
     *
     * @param string $abstract
     * @return \Closure|string|null
     */
    protected function getContextualConcrete($abstract)
    {
        if (isset($this->contextual[end($this->buildStack)][$abstract])) {
            return $this->contextual[end($this->buildStack)][$abstract];
        }

        return null;
    }

    /**
     * Normalize the given class name by removing leading slashes.
     *
     * @template T
     *
     * @param T $service
     * @return ($service is string ? string : T)
     */
    protected function normalize($service)
    {
        return is_string($service) ? ltrim($service, '\\') : $service;
    }

    /**
     * Get the extender callbacks for a given type.
     *
     * @param string $abstract
     * @return \Closure[]
     */
    protected function getExtenders($abstract)
    {
        if (isset($this->extenders[$abstract])) {
            return $this->extenders[$abstract];
        }

        return [];
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @template T of object
     *
     * @param \Closure|class-string<T>|string $concrete
     * @param array<string|int, mixed> $parameters
     *
     * @throws \IPE\Exception\InvalidArgument\InContainer
     *
     * @return ($concrete is class-string<T> ? T : mixed)
     */
    public function build($concrete, array $parameters = [])
    {
        // If the concrete type is actually a Closure, we will just execute it and
        // hand back the results of the functions, which allows functions to be
        // used as resolvers for more fine-tuned resolution of these objects.
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }
        if (!class_exists($concrete)) {
            throw new Exception\InvalidArgument\InContainer("Target [{$concrete}] is not a class.");
        }

        $reflector = new \ReflectionClass($concrete);

        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface of Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (!$reflector->isInstantiable()) {
            if ($this->buildStack !== []) {
                $previous = implode(', ', $this->buildStack);
                $message = "Target [{$concrete}] is not instantiable while building [{$previous}].";
            } else {
                $message = "Target [{$concrete}] is not instantiable.";
            }

            throw new Exception\InvalidArgument\InContainer($message);
        }

        $this->buildStack[] = $concrete;

        $constructor = $reflector->getConstructor();

        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if ($constructor === null) {
            array_pop($this->buildStack);

            return new $concrete();
        }

        $dependencies = $constructor->getParameters();

        // Once we have all the constructor's parameters we can create each of the
        // dependency instances and then use the reflection instances to make a
        // new instance of this class, injecting the created dependencies in.
        $parameters = $this->keyParametersByArgument(
            $dependencies,
            $parameters
        );

        $instances = $this->getDependencies(
            $dependencies,
            $parameters
        );

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param \ReflectionParameter[] $parameters
     * @param array<string|int, mixed> $primitives
     *
     * @return list<mixed>
     */
    protected function getDependencies(array $parameters, array $primitives = [])
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // If the class is null, it means the dependency is a string or some other
            // primitive type which we can not resolve since it is not a class and
            // we will just bomb out with an error since we have no-where to go.
            if (array_key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
            } elseif ($this->getParameterClassName($parameter) === '') {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }

        return $dependencies;
    }

    /**
     * Resolve a non-class hinted dependency.
     *
     * @throws \IPE\Exception\InvalidArgument\InContainer
     *
     * @return mixed
     */
    protected function resolveNonClass(\ReflectionParameter $parameter)
    {
        if (null !== ($concrete = $this->getContextualConcrete('$' . $parameter->name))) {
            if ($concrete instanceof \Closure) {
                return call_user_func($concrete, $this);
            }

            return $concrete;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = "Unresolvable dependency resolving [{$parameter}]";
        $declaringClass = $parameter->getDeclaringClass();
        if ($declaringClass !== null) {
            $message .= " in class {$declaringClass->getName()}";
        }

        throw new Exception\InvalidArgument\InContainer($message);
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @throws \IPE\Exception\InvalidArgument\InContainer
     *
     * @return mixed
     */
    protected function resolveClass(\ReflectionParameter $parameter)
    {
        try {
            return $this->make($this->getParameterClassName($parameter));
        }

        // If we can not resolve the class instance, we will check to see if the value
        // is optional, and if it is we will return the optional parameter value as
        // the value of the dependency, similarly to how we do this with scalars.
        catch (Exception\InvalidArgument\InContainer $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * If extra parameters are passed by numeric ID, rekey them by argument name.
     *
     * @param \ReflectionParameter[] $dependencies
     * @param array<string|int, mixed> $parameters
     * @return array<string|int, mixed>
     */
    protected function keyParametersByArgument(array $dependencies, array $parameters)
    {
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                unset($parameters[$key]);

                $parameters[$dependencies[$key]->name] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Register a new resolving callback.
     *
     * @param \Closure|string $abstract
     * @param \Closure|null $callback
     *
     * @return void
     */
    public function resolving($abstract, $callback = null)
    {
        if ($abstract instanceof \Closure) {
            $this->resolvingCallback($abstract);
        } elseif ($callback !== null) {
            $this->resolvingCallbacks[$this->normalize($abstract)][] = $callback;
        }
    }

    /**
     * Register a new after resolving callback for all types.
     *
     * @param \Closure|string $abstract
     * @param \Closure|null $callback
     *
     * @return void
     */
    public function afterResolving($abstract, $callback = null)
    {
        if ($abstract instanceof \Closure) {
            $this->afterResolvingCallback($abstract);
        } elseif ($callback !== null) {
            $this->afterResolvingCallbacks[$this->normalize($abstract)][] = $callback;
        }
    }

    /**
     * Register a new resolving callback by type of its first argument.
     *
     * @return void
     */
    protected function resolvingCallback(\Closure $callback)
    {
        $abstract = $this->getFunctionHint($callback);

        if ($abstract) {
            $this->resolvingCallbacks[$abstract][] = $callback;
        } else {
            $this->globalResolvingCallbacks[] = $callback;
        }
    }

    /**
     * Register a new after resolving callback by type of its first argument.
     *
     * @return void
     */
    protected function afterResolvingCallback(\Closure $callback)
    {
        $abstract = $this->getFunctionHint($callback);

        if ($abstract) {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        } else {
            $this->globalAfterResolvingCallbacks[] = $callback;
        }
    }

    /**
     * Get the type hint for this closure's first argument.
     *
     * @return string|null
     */
    protected function getFunctionHint(\Closure $callback)
    {
        $function = new \ReflectionFunction($callback);

        if ($function->getNumberOfParameters() === 0) {
            return null;
        }

        $expected = $function->getParameters()[0];

        $className = $this->getParameterClassName($expected);

        return $className === '' ? null : $className;
    }

    /**
     * Fire all of the resolving callbacks.
     *
     * @param string $abstract
     * @param mixed $object
     * @return void
     */
    protected function fireResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getCallbacksForType(
                $abstract,
                $object,
                $this->resolvingCallbacks
            )
        );

        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray(
            $object,
            $this->getCallbacksForType(
                $abstract,
                $object,
                $this->afterResolvingCallbacks
            )
        );
    }

    /**
     * Get all callbacks for a given type.
     *
     * @param string $abstract
     * @param mixed $object
     * @param array<string, \Closure[]> $callbacksPerType
     *
     * @return \Closure[]
     */
    protected function getCallbacksForType($abstract, $object, array $callbacksPerType)
    {
        $results = [];

        foreach ($callbacksPerType as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }

        return $results;
    }

    /**
     * Fire an array of callbacks with an object.
     *
     * @param mixed $object
     * @param \Closure[] $callbacks
     * @return void
     */
    protected function fireCallbackArray($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($object, $this);
        }
    }

    /**
     * Determine if a given type is shared.
     *
     * @param string $abstract
     * @return bool
     */
    public function isShared($abstract)
    {
        $abstract = $this->normalize($abstract);

        if (isset($this->instances[$abstract])) {
            return true;
        }

        if (!isset($this->bindings[$abstract]['shared'])) {
            return false;
        }

        return $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $abstract
     * @return string
     */
    protected function getAlias($abstract)
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * Get the container's bindings.
     *
     * @return array<string, array{concrete: \Closure, shared: bool}>
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Drop all of the stale instances and aliases.
     *
     * @param string $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetInstance($abstract)
    {
        unset($this->instances[$this->normalize($abstract)]);
    }

    /**
     * Clear all of the instances from the container.
     *
     * @return void
     */
    public function forgetInstances()
    {
        $this->instances = [];
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
    }

    /**
     * @return string the class name, or an empty string if the parameter has no class type hint
     */
    private function getParameterClassName(\ReflectionParameter $param)
    {
        if (\PHP_VERSION_ID < 80000) {
            $class = $param->getClass();

            return $class ? $class->name : '';
        }
        $paramType = $param->getType();
        if (!$paramType instanceof \ReflectionNamedType) {
            return '';
        }
        if ($paramType->isBuiltin()) {
            return '';
        }
        $name = $paramType->getName();
        switch (strtolower($name)) {
            case 'self':
                $declaring = $param->getDeclaringClass();

                return $declaring ? $declaring->getName() : '';

            case 'parent':
                $declaring = $param->getDeclaringClass();
                $parent = $declaring ? $declaring->getParentClass() : null;

                return $parent ? $parent->getName() : '';

            default:
                return $name;
        }
    }
}
