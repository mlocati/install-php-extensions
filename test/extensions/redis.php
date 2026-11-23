<?php

require_once __DIR__ . '/_bootstrap.php';

/**
 * Same rules as IPE\SystemDependencies\liblzf::isProvidedByOS().
 *
 * @return bool
 */
function isLZFProvidedByOS()
{
    $osRelease = is_file('/etc/os-release') ? (string) file_get_contents('/etc/os-release') : '';
    $id = preg_match('/^ID="?([^"\n]*)/m', $osRelease, $match) ? $match[1] : '';
    $version = preg_match('/^VERSION_ID="?([^"\n]*)/m', $osRelease, $match) ? $match[1] : '';
    switch ($id) {
        case 'alpine':
            return version_compare($version, '3.16') >= 0;
        case 'debian':
            // Testing and unstable don't have a VERSION_ID
            return $version === '' || version_compare($version, '11') >= 0;
        case 'ubuntu':
            return version_compare($version, '20.04') >= 0;
        case 'fedora':
        case 'almalinux':
        case 'centos':
        case 'ol':
        case 'rhel':
        case 'rocky':
            return true;
    }

    return false;
}

echo 'Checking redis... ';

new Redis();
$origin = getInstalledExtensionOrigin('redis');
// The packages of RHEL-like systems are built without LZF, and the GitHub archives (used by Packagist) don't include liblzf
if (!defined('Redis::COMPRESSION_LZF') && $origin !== 'system package' && ($origin === 'pecl' || isLZFProvidedByOS())) {
    throw new TestFailedException('LZF support has not been enabled');
}
if (extension_loaded('igbinary') && !defined('Redis::SERIALIZER_IGBINARY')) {
    throw new TestFailedException('igbinary support has not been enabled');
}

echo "passed.\n";
