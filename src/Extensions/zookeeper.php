<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\Service\Regex;
use IPE\SystemDependencies;

final class zookeeper extends Extension implements
    Extension\AvailableOnPECL,
    Extension\DeclaresDefaultVersion,
    Extension\DeclaresThreadSafetySupport,
    Extension\PatchesSourceCode,
    Extension\RequiresSystemDependencies
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'zookeeper';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['5.5', '5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['zookeeper'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLHandle()
     */
    public static function getPECLHandle()
    {
        return 'zookeeper';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\AvailableOnPECL::getPECLCategory()
     */
    public static function getPECLCategory()
    {
        return 'Configuration';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresDefaultVersion::getDefaultVersionRequirements()
     */
    public function getDefaultVersionRequirements()
    {
        return $this->php->getMajorMinorVersion() <= 506 ? '0.5.0' : '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresSystemDependencies::getRequiredSystemDependencies()
     */
    public function getRequiredSystemDependencies($version, Option\Values $options)
    {
        return [SystemDependencies\libzookeeper::HANDLE];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        // In ZTS builds the extension uses EG(vm_interrupt) as a zend_bool, but it's a zend_atomic_bool since PHP 8.2
        return $this->php->getMajorMinorVersion() < 802;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\PatchesSourceCode::patchSourceCode()
     */
    public function patchSourceCode($sourceDirectory)
    {
        $fs = $this->os->getFilesystem();
        // Old versions call php_add_tick_function() without including its header
        $file = "{$sourceDirectory}/php_zookeeper.c";
        $code = $fs->readFile($file);
        if (strpos($code, 'php_ticks.h') === false) {
            $fs->writeFile($file, Regex::replace('/^#include\s*[<"]php\.h[>"]/m', "\$0\n#include <php_ticks.h>", $code, 1));
        }
        // Old versions link the multi-threaded library without defining THREADED, which ZooKeeper 3.5+ requires to declare its functions (not all the sources include config.h)
        $file = "{$sourceDirectory}/config.m4";
        $original = $fs->readFile($file);
        $code = $original;
        if (strpos($code, 'THREADED') === false) {
            $code = Regex::replace('/^([ \t]*)PHP_ADD_LIBRARY_WITH_PATH\(zookeeper_mt,[^\r\n]*/m', "\$0\n\${1}CFLAGS=\"\$CFLAGS -DTHREADED\"", $code, 1);
        }
        // Old versions only know ZOO_MAJOR_VERSION and friends, which ZooKeeper 3.6+ replaced with ZOO_VERSION
        if (strpos($code, 'ZOO_VERSION') === false) {
            $code = Regex::replace('/^([ \t]*)if test \$\{ZOO_MINOR_VERSION\} -eq 5; then/m', "\${1}if grep -q 'define ZOO_VERSION ' \"\$PHP_LIBZOOKEEPER_INCDIR/zookeeper_version.h\" || test \"\$ZOO_MINOR_VERSION\" = 5; then", $code);
        }
        if ($code !== $original) {
            $fs->writeFile($file, $code);
        }
        foreach ($fs->listDirectoryContents($sourceDirectory) as $item) {
            $name = basename($item);
            if (!preg_match('/\.[ch]$/D', $name)) {
                continue;
            }
            $file = "{$sourceDirectory}/{$name}";
            $code = $fs->readFile($file);
            if (strpos($code, 'ZOO_VERSION') !== false) {
                continue;
            }
            $patched = Regex::replace('/^#if[ \t]+(ZOO_MAJOR_VERSION[ \t]*>=[ \t]*3[ \t]*&&[ \t]*ZOO_MINOR_VERSION[ \t]*>=[ \t]*5)\b/m', '#if defined(ZOO_VERSION) || ($1)', $code);
            $patched = Regex::replace('/^([ \t]*)snprintf\(buf, sizeof\(buf\), "%ld\.%ld\.%ld", ZOO_MAJOR_VERSION,[^\r\n]*/m', "#ifdef ZOO_VERSION\n\${1}snprintf(buf, sizeof(buf), \"%s\", ZOO_VERSION);\n#else\n\$0\n#endif", $patched);
            if ($patched !== $code) {
                $fs->writeFile($file, $patched);
            }
        }
    }
}
