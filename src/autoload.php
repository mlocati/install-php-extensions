<?php

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'IPE\\') === 0) {
            $file = __DIR__ . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('IPE'))) . '.php';
        } elseif (strpos($class, 'Composer\\Semver\\') === 0) {
            $file = __DIR__ . '/../vendor/composer/semver/src/' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('Composer\\Semver\\'))) . '.php';
        } else {
            return;
        }
        if (is_file($file)) {
            require_once $file;
        }
    }
);
