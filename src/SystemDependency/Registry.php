<?php

namespace IPE\SystemDependency;

use IPE\Exception;
use IPE\Installer\State;
use IPE\Option\Validator;
use IPE\Service\Container;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class Registry
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @var \IPE\Service\Container
     */
    private $app;

    /**
     * @var array<string, class-string<\IPE\SystemDependency>> the keys are the handles of the dependencies
     */
    private $classes = [];

    /**
     * The instances are created only when needed, since that requires detecting PHP and the operating system.
     *
     * @var array<string, \IPE\SystemDependency>
     */
    private $dependencies = [];

    /**
     * The handles of the instances that have been created by this class, and that depend on the options.
     *
     * @var array<string, true>
     */
    private $builtHandles = [];

    /**
     * @var \IPE\Installer\State|null
     */
    private $state = null;

    /**
     * @var \IPE\Option\Validator
     */
    private $optionValidator;

    public function __construct(Container $app, Validator $optionValidator, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->app = $app;
        $this->optionValidator = $optionValidator;
    }

    /**
     * @return $this
     */
    public function registerDefaultDependencies()
    {
        foreach ($this->listDependencyClassesIn(__DIR__ . '/../SystemDependencies/', 'IPE\\SystemDependencies\\') as $className) {
            $this->registerClass($className);
        }

        return $this;
    }

    /**
     * Set the options to be passed to the dependencies, checking them against the registered dependencies so that invalid options are detected before installing anything.
     *
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return void
     */
    public function setOptions(State $state)
    {
        foreach ($this->classes as $className) {
            $this->createOptions($className, $state);
        }
        $unknownHandles = array_diff($state->getHandlesWithSystemDependencyOptions(), array_keys($this->classes));
        if ($unknownHandles !== []) {
            throw new Exception\InvalidOptionValues('Options specified for unknown system dependencies: ' . implode(', ', $unknownHandles));
        }
        $this->state = $state;
        $this->dependencies = array_diff_key($this->dependencies, $this->builtHandles);
        $this->builtHandles = [];
    }

    /**
     * @param class-string<\IPE\SystemDependency> $className
     *
     * @throws \IPE\Exception\InvalidArgument if there's already a registered dependency with the handle of $className
     *
     * @return $this
     */
    public function registerClass($className)
    {
        $handle = $className::getHandle();
        if (isset($this->classes[$handle])) {
            throw new Exception\InvalidArgument("There's already a registered system dependency with handle '{$handle}'");
        }
        $this->classes[$handle] = $className;
        ksort($this->classes);

        return $this;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if there's already a registered dependency with the handle of $dependency
     *
     * @return $this
     */
    public function registerDependency(SystemDependency $dependency)
    {
        $this->registerClass(get_class($dependency));
        $this->dependencies[$dependency::getHandle()] = $dependency;

        return $this;
    }

    /**
     * @return list<class-string<\IPE\SystemDependency>>
     */
    public function getDependencyClasses()
    {
        return array_values($this->classes);
    }

    /**
     * @param non-empty-string $handle
     *
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return \IPE\SystemDependency|null
     */
    public function getDependencyByHandle($handle)
    {
        if (!isset($this->classes[$handle])) {
            return null;
        }
        if (!isset($this->dependencies[$handle])) {
            $this->dependencies[$handle] = $this->app->make($this->classes[$handle], ['options' => $this->createOptions($this->classes[$handle], $this->state)]);
            $this->builtHandles[$handle] = true;
        }

        return $this->dependencies[$handle];
    }

    /**
     * Get the dependencies with the specified handles, plus the ones they require, ordered so that every dependency comes after the ones it requires.
     * Unknown handles are logged and assumed to be already available.
     *
     * @param list<non-empty-string> $handles
     *
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return list<\IPE\SystemDependency>
     */
    public function resolveDependencies(array $handles)
    {
        $resolved = [];
        foreach ($handles as $handle) {
            $this->addResolved($handle, $resolved, []);
        }

        return array_values($resolved);
    }

    /**
     * @param list<\IPE\SystemDependency> $dependencies
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Packages
     */
    public function collectSystemPackages(array $dependencies, State $state)
    {
        $result = new Packages();
        foreach ($dependencies as $dependency) {
            $result = $result->merge($dependency->getSystemPackages($state));
        }

        return $result;
    }

    /**
     * @param class-string<\IPE\SystemDependency> $className
     * @param \IPE\Installer\State|null $state
     *
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return \IPE\Option\Values
     */
    private function createOptions($className, $state)
    {
        $handle = $className::getHandle();

        return $this->optionValidator->validate(
            $state === null ? [] : $state->getSystemDependencyUserOptions($handle),
            $className::getOptions(),
            false,
            "the system dependency {$handle}"
        );
    }

    /**
     * Depth-first visit: the required dependencies are added before the one that requires them.
     *
     * @param non-empty-string $handle
     * @param array<non-empty-string, \IPE\SystemDependency> $resolved
     * @param list<non-empty-string> $visiting the handles being resolved (to detect circular requirements)
     *
     * @throws \IPE\Exception\InvalidArgument in case of circular requirements
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function addResolved($handle, array &$resolved, array $visiting)
    {
        if (isset($resolved[$handle])) {
            return;
        }
        if (in_array($handle, $visiting, true)) {
            throw new Exception\InvalidArgument('Circular system dependencies: ' . implode(' -> ', $visiting) . " -> {$handle}");
        }
        $dependency = $this->getDependencyByHandle($handle);
        if ($dependency === null) {
            $this->logger->warning("No system packages are known for the dependency '{$handle}': assuming it's already available");

            return;
        }
        $visiting[] = $handle;
        foreach ($dependency->getRequiredDependencies() as $required) {
            $this->addResolved($required, $resolved, $visiting);
        }
        $resolved[$handle] = $dependency;
    }

    /**
     * @param string $dirPrefix
     * @param string $namespacePrefix
     *
     * @return list<class-string<\IPE\SystemDependency>>
     */
    private function listDependencyClassesIn($dirPrefix, $namespacePrefix)
    {
        $result = [];
        $matches = null;
        foreach (scandir($dirPrefix) as $itemName) {
            if ($itemName[0] === '.') {
                continue;
            }
            $itemPath = $dirPrefix . $itemName;
            if (is_dir($itemPath)) {
                $result = array_merge($result, $this->listDependencyClassesIn($itemPath . '/', $namespacePrefix . $itemName . '\\'));
            } elseif (preg_match('/^(?<className>.+)\.php$/D', $itemName, $matches)) {
                $className = $namespacePrefix . $matches['className'];
                if (class_exists($className) && is_subclass_of($className, SystemDependency::class)) {
                    $classInfo = new \ReflectionClass($className);
                    if ($classInfo->isInstantiable()) {
                        $result[] = $className;
                    }
                }
            }
        }

        return $result;
    }
}
