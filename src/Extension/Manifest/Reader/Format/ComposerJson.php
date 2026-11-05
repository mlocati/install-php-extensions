<?php

namespace IPE\Extension\Manifest\Reader\Format;

use IPE\Exception;
use IPE\Extension\Manifest;
use IPE\Extension\Manifest\Reader\Format;
use IPE\Option\ConfigureOption;
use IPE\Service\JSON;
use IPE\Service\Regex;

/**
 * Reads the extension metadata from a composer.json file.
 *
 * @see https://github.com/php/pie/blob/1.6.x/resources/composer-json-php-ext-schema.json
 */
final class ComposerJson extends Format
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Manifest\Reader\Format::getFileNames()
     */
    public function getFileNames()
    {
        return ['composer.json'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Manifest\Reader\Format::parse()
     */
    public function parse($content)
    {
        if (trim($content) === '') {
            throw new Exception\InvalidArgument('Empty composer.json');
        }
        try {
            $data = JSON::decode($content, true);
        } catch (Exception\JSON $x) {
            throw new Exception\InvalidArgument("Failed to decode composer.json:\n{$x->getMessage()}");
        }
        if (!is_array($data)) {
            throw new Exception\InvalidArgument('Failed to decode composer.json');
        }

        return $this->parseData($data);
    }

    /**
     * Read the extension metadata from the decoded composer.json (for example, the metadata published in a Composer repository).
     *
     * @param array<mixed> $data
     *
     * @return \IPE\Extension\Manifest|null null if it's not the composer.json of a PHP extension
     */
    public function parseData(array $data)
    {
        $type = self::getString($data, 'type');
        if (!in_array($type, ['php-ext', 'php-ext-zend'], true)) {
            return null;
        }
        $phpExt = isset($data['php-ext']) && is_array($data['php-ext']) ? $data['php-ext'] : [];
        $packageName = self::getString($data, 'name');
        $extensionName = self::getString($phpExt, 'extension-name');
        if ($extensionName === '' && $packageName !== '') {
            $chunks = explode('/', $packageName);
            $extensionName = array_pop($chunks);
        }
        $manifest = new Manifest(Manifest::TYPE_COMPOSER, $packageName, Regex::replace('/^ext-/i', '', $extensionName));
        $manifest
            ->setVersion(self::getString($data, 'version'))
            ->setZendExtension($type === 'php-ext-zend')
            ->setRawData($data)
        ;
        $require = isset($data['require']) && is_array($data['require']) ? $data['require'] : [];
        foreach ($require as $name => $constraint) {
            if ($name === 'php') {
                $manifest->setPHPConstraint(is_string($constraint) ? $constraint : '');
            } elseif (is_string($name) && stripos($name, 'ext-') === 0 && strlen($name) > 4) {
                $manifest->addRequiredExtension(substr($name, 4), is_string($constraint) ? $constraint : '*');
            } elseif (is_string($name) && stripos($name, 'lib-') === 0 && strlen($name) > 4) {
                $manifest->addRequiredLibrary(substr($name, 4), is_string($constraint) ? $constraint : '*');
            }
        }
        $suggest = isset($data['suggest']) && is_array($data['suggest']) ? $data['suggest'] : [];
        foreach (array_keys($suggest) as $name) {
            if (is_string($name) && stripos($name, 'ext-') === 0 && strlen($name) > 4) {
                $manifest->addOptionalExtension(substr($name, 4));
            }
        }
        $conflict = isset($data['conflict']) && is_array($data['conflict']) ? $data['conflict'] : [];
        foreach ($conflict as $name => $constraint) {
            if (is_string($name) && stripos($name, 'ext-') === 0 && strlen($name) > 4) {
                $manifest->addConflictingExtension(substr($name, 4), is_string($constraint) ? $constraint : '*');
            }
        }
        if (isset($phpExt['support-zts']) && is_bool($phpExt['support-zts'])) {
            $manifest->setSupportsZTS($phpExt['support-zts']);
        }
        if (isset($phpExt['support-nts']) && is_bool($phpExt['support-nts'])) {
            $manifest->setSupportsNTS($phpExt['support-nts']);
        }
        if (isset($phpExt['priority']) && is_int($phpExt['priority']) && $phpExt['priority'] >= 10 && $phpExt['priority'] <= 99) {
            $manifest->setPriority($phpExt['priority']);
        }
        $manifest->setBuildPath(self::getString($phpExt, 'build-path'));
        if (isset($phpExt['os-families']) && is_array($phpExt['os-families'])) {
            $manifest->setOSFamilies(array_values(array_filter($phpExt['os-families'], 'is_string')));
        }
        if (isset($phpExt['os-families-exclude']) && is_array($phpExt['os-families-exclude'])) {
            $manifest->setOSFamiliesExclude(array_values(array_filter($phpExt['os-families-exclude'], 'is_string')));
        }
        if (isset($phpExt['configure-options']) && is_array($phpExt['configure-options'])) {
            foreach ($phpExt['configure-options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $name = self::getString($option, 'name');
                if ($name === '') {
                    continue;
                }
                $manifest->addConfigureOption(new ConfigureOption(
                    $name,
                    self::getString($option, 'description'),
                    isset($option['needs-value']) && is_bool($option['needs-value']) ? $option['needs-value'] : false
                ));
            }
        }

        return $manifest;
    }

    /**
     * @param array<mixed> $data
     * @param string $key
     *
     * @return string
     */
    private static function getString(array $data, $key)
    {
        return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
    }
}
