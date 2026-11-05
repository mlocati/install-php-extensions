<?php

namespace IPE\Extension\Manifest\Reader\Format;

use IPE\Exception;
use IPE\Extension\Manifest;
use IPE\Extension\Manifest\Reader\Format;
use IPE\Option\ConfigureOption;
use IPE\Service\BasicXmlParser;
use IPE\Service\BasicXmlParser\Element;

/**
 * Reads the extension metadata from a package.xml / package2.xml file (PEAR/PECL packages).
 *
 * @see https://pear.php.net/dtd/package-1.0.xsd
 * @see https://pear.php.net/dtd/package-2.0.xsd
 */
final class PackageXml extends Format
{
    /**
     * @var \IPE\Service\BasicXmlParser
     */
    private $xmlParser;

    public function __construct(\IPE\Logger $logger, BasicXmlParser $xmlParser)
    {
        parent::__construct($logger);
        $this->xmlParser = $xmlParser;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Manifest\Reader\Format::getFileNames()
     */
    public function getFileNames()
    {
        return ['package2.xml', 'package.xml'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Manifest\Reader\Format::parse()
     */
    public function parse($content)
    {
        if (trim($content) === '') {
            throw new Exception\InvalidArgument('Empty package.xml');
        }
        $root = $this->xmlParser->parse($content);
        if ($root->getName() !== 'package') {
            return null;
        }
        $packageName = $root->getChildText('name');
        if ($packageName === '') {
            return null;
        }
        switch ($this->detectSchemaVersion($root)) {
            case 1:
                return $this->parseV1($root, $packageName);
            case 2:
                return $this->parseV2($root, $packageName);
        }

        return null;
    }

    /**
     * @return int 1, 2 or 0 (unknown)
     */
    private function detectSchemaVersion(Element $root)
    {
        $version = $root->getAttribute('version');
        if ($version !== '') {
            return (int) $version;
        }
        if ($root->find('version/release') !== null) {
            return 2;
        }
        if ($root->find('release/version') !== null) {
            return 1;
        }

        return 0;
    }

    /**
     * @param non-empty-string $packageName
     *
     * @return \IPE\Extension\Manifest
     */
    private function parseV2(Element $root, $packageName)
    {
        $extensionName = $root->getChildText('providesextension');
        $manifest = new Manifest(Manifest::TYPE_PECL, $packageName, $extensionName === '' ? $packageName : $extensionName);
        $manifest
            ->setVersion(self::findText($root, 'version/release'))
            ->setZendExtension($root->getChild('zendextsrcrelease') !== null || $root->getChild('zendextbinrelease') !== null)
            ->setRawData($root->toArray())
        ;
        $php = $root->find('dependencies/required/php');
        if ($php !== null) {
            $manifest->setPHPConstraint(self::buildConstraint($php));
        }
        foreach ($root->findAll('dependencies/required/extension') as $extension) {
            $name = $extension->getChildText('name');
            if ($name === '') {
                continue;
            }
            if ($extension->getChild('conflicts') !== null) {
                $manifest->addConflictingExtension($name, self::buildConstraint($extension));
            } else {
                $manifest->addRequiredExtension($name, self::buildConstraint($extension));
            }
        }
        foreach ($root->findAll('dependencies/optional/extension') as $extension) {
            $name = $extension->getChildText('name');
            if ($name !== '') {
                $manifest->addOptionalExtension($name, self::buildConstraint($extension));
            }
        }
        $osFamilies = [];
        $osFamiliesExclude = [];
        foreach ($root->findAll('dependencies/required/os') as $os) {
            $name = $os->getChildText('name');
            if ($name === '') {
                continue;
            }
            if ($os->getChild('conflicts') !== null) {
                $osFamiliesExclude[] = $name;
            } else {
                $osFamilies[] = $name;
            }
        }
        $manifest->setOSFamilies($osFamilies)->setOSFamiliesExclude($osFamiliesExclude);
        foreach ($root->findAll('extsrcrelease/configureoption') as $option) {
            $this->addConfigureOption($manifest, $option);
        }

        return $manifest;
    }

    /**
     * @param non-empty-string $packageName
     *
     * @return \IPE\Extension\Manifest
     */
    private function parseV1(Element $root, $packageName)
    {
        $release = $root->getChild('release');
        $extensionName = '';
        if ($release !== null) {
            foreach ($release->getChildrenByName('provides') as $provides) {
                if ($provides->getAttribute('type') === 'ext' && $provides->getAttribute('name') !== '') {
                    $extensionName = $provides->getAttribute('name');
                    break;
                }
            }
        }
        $manifest = new Manifest(Manifest::TYPE_PECL, $packageName, $extensionName === '' ? $packageName : $extensionName);
        $manifest
            ->setVersion(self::findText($root, 'release/version'))
            ->setRawData($root->toArray())
        ;
        $phpConstraints = [];
        $osFamilies = [];
        foreach ($root->findAll('release/deps/dep') as $dep) {
            $constraint = self::buildConstraintV1($dep);
            switch ($dep->getAttribute('type')) {
                case 'php':
                    if ($constraint !== '*') {
                        $phpConstraints[] = $constraint;
                    }
                    break;
                case 'ext':
                    if ($dep->getText() !== '') {
                        if (strtolower($dep->getAttribute('rel')) === 'not') {
                            $manifest->addConflictingExtension($dep->getText());
                        } elseif (strtolower($dep->getAttribute('optional')) === 'yes') {
                            $manifest->addOptionalExtension($dep->getText(), $constraint);
                        } else {
                            $manifest->addRequiredExtension($dep->getText(), $constraint);
                        }
                    }
                    break;
                case 'ldlib':
                case 'ltlib':
                    if ($dep->getText() !== '') {
                        $manifest->addRequiredLibrary($dep->getText(), $constraint);
                    }
                    break;
                case 'os':
                    if ($dep->getText() !== '') {
                        $osFamilies[] = $dep->getText();
                    }
                    break;
            }
        }
        $manifest->setPHPConstraint(implode(' ', $phpConstraints))->setOSFamilies($osFamilies);
        foreach ($root->findAll('release/configureoptions/configureoption') as $option) {
            $this->addConfigureOption($manifest, $option);
        }

        return $manifest;
    }

    /**
     * @return void
     */
    private function addConfigureOption(Manifest $manifest, Element $option)
    {
        $name = $option->getAttribute('name');
        if ($name === '') {
            return;
        }
        $manifest->addConfigureOption(new ConfigureOption(
            $name,
            $option->getAttribute('prompt'),
            null,
            $option->hasAttribute('default') ? $option->getAttribute('default') : null
        ));
    }

    /**
     * Build a composer constraint from the min/max/exclude children of a package.xml 2.0 dependency.
     *
     * @return non-empty-string
     */
    private static function buildConstraint(Element $dependency)
    {
        $parts = [];
        $min = $dependency->getChildText('min');
        if ($min !== '') {
            $parts[] = ">={$min}";
        }
        $max = $dependency->getChildText('max');
        if ($max !== '') {
            $parts[] = "<={$max}";
        }
        foreach ($dependency->getChildrenByName('exclude') as $exclude) {
            if ($exclude->getText() !== '') {
                $parts[] = "!={$exclude->getText()}";
            }
        }

        return $parts === [] ? '*' : implode(' ', $parts);
    }

    /**
     * Build a composer constraint from the rel/version attributes of a package.xml 1.0 dependency.
     *
     * @return non-empty-string
     */
    private static function buildConstraintV1(Element $dependency)
    {
        $version = $dependency->getAttribute('version');
        if ($version === '') {
            return '*';
        }
        switch (strtolower($dependency->getAttribute('rel'))) {
            case 'eq':
                return $version;
            case 'ne':
                return "!={$version}";
            case 'lt':
                return "<{$version}";
            case 'le':
                return "<={$version}";
            case 'gt':
                return ">{$version}";
            case 'ge':
                return ">={$version}";
        }

        return '*';
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function findText(Element $root, $path)
    {
        $element = $root->find($path);

        return $element === null ? '' : $element->getText();
    }
}
