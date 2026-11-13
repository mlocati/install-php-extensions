<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gd... ';

function destroyImage($image)
{
    if (PHP_VERSION_ID < 80000) {
        imagedestroy($image);
    }
}

$width = 8;
$height = 16;
$formats = [
    'gd' => [true, true],
    'gd2' => [true, true],
    'gif' => [true, true],
    'jpeg' => [true, true],
    'png' => [true, true],
    'wbmp' => [true, true],
    'webp' => [true, true],
    'xbm' => [true, true],
    'xpm' => [true, false],
];
if (PHP_VERSION_ID >= 70200) {
    $formats['bmp'] = [true, true];
    // The gd packages of some distributions (like AlmaLinux) are built without AVIF
    if (PHP_VERSION_ID >= 80100 && getInstalledExtensionOrigin('gd') !== 'system package') {
        $formats['avif'] = [true, true];
    }
}
foreach ($formats as $format => list($canRead, $canWrite)) {
    if ($canRead) {
        $read = "imagecreatefrom{$format}";
        if (!function_exists($read)) {
            throw new TestFailedException("The function {$read}() is missing");
        }
    }
    if ($canWrite) {
        $write = "image{$format}";
        if (!function_exists($write)) {
            throw new TestFailedException("The function {$write}() is missing");
        }
        // A new image for each format: before PHP 7.2, imagegd() converts it to a palette one, which imagewebp() doesn't support.
        $image = imagecreatetruecolor($width, $height);
        $file = tempnam(sys_get_temp_dir(), 'ipe');
        try {
            ob_start();
            $saved = $write($image, $file);
            $output = ob_get_clean();
            destroyImage($image);
            if ($format === 'xbm' && PHP_VERSION_ID < 50600 && filesize($file) === 0) {
                // Before PHP 5.6, imagexbm() prints the image
                file_put_contents($file, $output);
                clearstatcache();
            }
            if ($saved === false || filesize($file) === 0) {
                throw new TestFailedException("{$write}() failed");
            }
            if ($canRead) {
                $loaded = $read($file);
                if ($loaded === false || imagesx($loaded) !== $width || imagesy($loaded) !== $height) {
                    throw new TestFailedException("{$read}() failed");
                }
                destroyImage($loaded);
            }
        } finally {
            unlink($file);
        }
    } elseif ($canRead) {
        $loaded = $read(__DIR__ . "/gd/sample.{$format}");
        if ($loaded === false || imagesx($loaded) !== $width || imagesy($loaded) !== $height) {
            throw new TestFailedException("{$read}() failed");
        }
        destroyImage($loaded);
    }
}

$otherFunctions = [
    'imageantialias',
    'imagefttext',
];

foreach ($otherFunctions as $function) {
    if (!function_exists($function)) {
        throw new TestFailedException("The function {$function}() is missing");
    }
}

echo "passed.\n";
