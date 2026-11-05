<?php

namespace IPE;

use IPE\Service\Container;

/**
 * @return \IPE\Service\Container
 */
function createApp()
{
    $app = new Container();
    $app->instance(Container::class, $app);
    // Always the same instance: use setTarget() to choose where the messages go
    $app->singleton(Logger::class);

    // Filesystem
    $app->singleton(Filesystem::class, static function (Container $app) {
        return $app->make(Filesystem\Local::class);
    });
    $app->bind(Filesystem\VolatileDirectory::class, static function (Container $app) {
        return $app->make(Filesystem::class)->createVolatileDirectory();
    });
    $app->singleton(Filesystem\Downloader::class, static function (Container $app) {
        return $app->make(Filesystem\Downloader\Factory::class)->buildDownloader();
    });
    $app->singleton(Filesystem\ArchiveExtractor::class);
    // Composer repositories
    $app->singleton(ComposerRepository\Registry::class, static function (Container $app) {
        return $app->build(ComposerRepository\Registry::class)->registerDefaultRepositories();
    });
    // Extension registry
    $app->singleton(Extension\Registry::class, static function (Container $app) {
        $registry = $app->build(Extension\Registry::class);
        $registry->registerDefaultExtensions();

        return $registry;
    });
    // PHP
    $app->singleton(PHPInfo::class, static function (Container $app) {
        $fs = $app->make(Filesystem::class);
        if ($fs->isLocal()) {
            return PHPInfo::fromCurrentPHP($fs);
        }

        return PHPInfo::fromPHPBin('php', $fs);
    });
    $app->singleton(Service\PHPSystemPackage::class);
    // OS
    $app->singleton(OS::class, static function (Container $app) {
        return $app->make(OS\Factory::class)->detectOS();
    });
    $app->singleton(OS\Posix::class, static function (Container $app) {
        $os = $app->make(OS::class);
        if (!$os instanceof OS\Posix) {
            throw new Exception\UnsupportedOS("Unsupported OS: {$os->getDisplayName()}");
        }

        return $os;
    });
    // System dependency registry
    $app->singleton(SystemDependency\Registry::class, static function (Container $app) {
        $registry = $app->build(SystemDependency\Registry::class);
        $registry->registerDefaultDependencies();

        return $registry;
    });
    // Extension Resolver
    $app->bind(Extension\Asked\Resolver::class, static function (Container $app) {
        $os = $app->make(OS::class);
        if ($os instanceof OS\Windows) {
            return $app->make(Extension\Asked\Resolver\Windows::class);
        }
        if ($os instanceof OS\Posix) {
            return $app->make(Extension\Asked\Resolver\Posix::class);
        }
        throw new Exception\UnsupportedOS('Unsupported OS: ' . $os->getDisplayName());
    });

    return $app;
}
