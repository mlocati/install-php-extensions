<?php

namespace IPE\Extension;

use IPE\Exception;
use IPE\Extension;
use IPE\Service\Container;

class Registry
{
    /**
     * @var \IPE\Service\Container
     */
    private $app;

    /**
     * @var array<non-empty-string, class-string<\IPE\Extension>> the keys are the handles of the extensions
     */
    private $classes = [];

    /**
     * The instances are created only when needed, since that requires detecting PHP and the operating system.
     *
     * @var array<string, \IPE\Extension>
     */
    private $extensions = [];

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @return $this
     */
    public function registerDefaultExtensions()
    {
        foreach ($this->listExtensionClassesIn(__DIR__ . '/../Extensions/', 'IPE\\Extensions\\') as $className) {
            $this->registerClass($className);
        }

        return $this;
    }

    /**
     * @param class-string<\IPE\Extension> $className
     *
     * @throws \IPE\Exception\InvalidArgument if there's already a registered extension with the handle of $className
     *
     * @return $this
     */
    public function registerClass($className)
    {
        $handle = $className::getHandle();
        if (isset($this->classes[$handle])) {
            throw new Exception\InvalidArgument("There's already a registered extension with handle '{$handle}'");
        }
        $this->classes[$handle] = $className;
        ksort($this->classes);

        return $this;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if there's already a registered extension with the handle of $extension
     *
     * @return $this
     */
    public function registerExtension(Extension $extension)
    {
        $this->registerClass(get_class($extension));
        $this->extensions[$extension::getHandle()] = $extension;

        return $this;
    }

    /**
     * @return list<class-string<\IPE\Extension>>
     */
    public function getExtensionClasses()
    {
        return array_values($this->classes);
    }

    /**
     * @param non-empty-string $handle
     *
     * @return \IPE\Extension|null
     */
    public function getExtensionByHandle($handle)
    {
        if (!isset($this->classes[$handle])) {
            return null;
        }
        if (!isset($this->extensions[$handle])) {
            $this->extensions[$handle] = $this->app->make($this->classes[$handle]);
        }

        return $this->extensions[$handle];
    }

    /**
     * @param non-empty-string $packageName
     *
     * @return \IPE\Extension|null
     */
    public function getExtensionByComposerPackageName($packageName)
    {
        foreach ($this->classes as $handle => $class) {
            foreach (self::getComposerPackageNames($class) as $name) {
                if (strcasecmp($name, $packageName) === 0) {
                    return $this->getExtensionByHandle($handle);
                }
            }
        }

        return null;
    }

    /**
     * Only the bundled extensions we know have a php/... name: other php/... packages may be published on Packagist.
     *
     * @param class-string<\IPE\Extension> $className
     *
     * @return list<non-empty-string> the Composer package names that identify an extension, sorted alphabetically
     */
    public static function getComposerPackageNames($className)
    {
        $result = [];
        if (is_subclass_of($className, AvailableAsComposerPackage::class)) {
            $result[] = $className::getComposerPackageName();
        }
        if (is_subclass_of($className, BundledInPHPSource::class)) {
            $result[] = BundledInPHPSource::COMPOSER_VENDOR . '/' . $className::getHandle();
        }
        $result = array_unique($result);
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param non-empty-string $peclHandle
     *
     * @return \IPE\Extension|null
     */
    public function getExtensionByPECLHandle($peclHandle)
    {
        foreach ($this->classes as $handle => $class) {
            if (is_subclass_of($class, AvailableOnPECL::class) && strcasecmp($class::getPECLHandle(), $peclHandle) === 0) {
                return $this->getExtensionByHandle($handle);
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $installedName the name of the extension as seen by PHP
     *
     * @return \IPE\Extension|null
     */
    public function getExtensionByInstalledName($installedName)
    {
        foreach ($this->classes as $handle => $class) {
            foreach ($class::getInstalledNames() as $name) {
                if (strcasecmp($name, $installedName) === 0) {
                    return $this->getExtensionByHandle($handle);
                }
            }
        }

        return null;
    }

    /**
     * Find the extension described by a manifest file.
     *
     * @return \IPE\Extension|null
     */
    public function getExtensionByManifest(Manifest $manifest)
    {
        $packageName = $manifest->getPackageName();
        if ($packageName !== '') {
            switch ($manifest->getType()) {
                case Manifest::TYPE_COMPOSER:
                    $extension = $this->getExtensionByComposerPackageName($packageName);
                    break;
                case Manifest::TYPE_PECL:
                    $extension = $this->getExtensionByPECLHandle($packageName);
                    break;
                default:
                    $extension = null;
                    break;
            }
            if ($extension !== null) {
                return $extension;
            }
        }
        $extensionName = $manifest->getExtensionName();

        return $extensionName === '' ? null : $this->getExtensionByInstalledName($extensionName);
    }

    /**
     * @param string $dirPrefix
     * @param string $namespacePrefix
     *
     * @return list<class-string<\IPE\Extension>>
     */
    private function listExtensionClassesIn($dirPrefix, $namespacePrefix)
    {
        $result = [];
        $matches = null;
        foreach (scandir($dirPrefix) as $itemName) {
            if ($itemName[0] === '.') {
                continue;
            }
            $itemPath = $dirPrefix . $itemName;
            if (is_dir($itemPath)) {
                $result = array_merge($result, $this->listExtensionClassesIn($itemPath . '/', $namespacePrefix . $itemName . '\\'));
            } elseif (preg_match('/^(?<className>.+)\.php$/D', $itemName, $matches)) {
                $className = $namespacePrefix . $matches['className'];
                if (class_exists($className) && is_subclass_of($className, Extension::class)) {
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
