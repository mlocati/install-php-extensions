<?php

namespace IPE\Test\Dev;

use IPE\Dev\DockerImage;
use IPE\Dev\DockerImages;
use IPE\Test\Support\TestCase;

final class DockerImagesTest extends TestCase
{
    /**
     * @return void
     */
    public function testImages()
    {
        $operatingSystems = DockerImages::getOperatingSystems();
        self::assertSame(array_values(array_unique($operatingSystems)), $operatingSystems);
        $images = DockerImages::getAll();
        $names = array_map(static function (DockerImage $image) { return $image->getName(); }, $images);
        self::assertSame(array_values(array_unique($names)), $names);
        $osIndexes = array_map(static function (DockerImage $image) use ($operatingSystems) { return array_search($image->getOS(), $operatingSystems, true); }, $images);
        self::assertSame([], array_keys($osIndexes, false, true), 'Unknown operating system');
        $sortedOSIndexes = $osIndexes;
        sort($sortedOSIndexes);
        self::assertSame($sortedOSIndexes, $osIndexes, 'The images must be sorted by operating system');
    }

    /**
     * Compares the images with the data of https://github.com/mlocati/docker-php-images-matrix.
     *
     * @group online
     *
     * @return void
     */
    public function testOfficialImagesAreUpToDate()
    {
        $context = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'install-php-extensions tests']]);
        $json = @file_get_contents('https://raw.githubusercontent.com/mlocati/docker-php-images-matrix/main/data/data.json', false, $context);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !isset($data['images']) || !is_array($data['images'])) {
            self::markTestSkipped('Failed to download the data of the official Docker PHP images');
        }
        $expected = [];
        foreach ($data['images'] as $phpVersion => $info) {
            $phpVersion = (string) $phpVersion;
            // Older PHP versions are not supported, PHP 5.5 is not in the data
            if (version_compare($phpVersion, '5.6') < 0) {
                continue;
            }
            if (!is_array($info) || !isset($info['isRC']) || !is_bool($info['isRC']) || !isset($info['os']) || !is_array($info['os'])) {
                self::fail("Unrecognized data for PHP {$phpVersion}");
            }
            $osList = [];
            foreach ($info['os'] as $os) {
                if (!is_array($os) || !isset($os['id']) || !is_string($os['id'])) {
                    self::fail("Unrecognized operating system for PHP {$phpVersion}");
                }
                // Alpine versions older than 3.9 are not supported
                if (!preg_match('/^alpine3\.[0-8]$/D', $os['id'])) {
                    $osList[] = $os['id'];
                }
            }
            sort($osList);
            $expected[$phpVersion] = ['rc' => $info['isRC'], 'os' => $osList];
        }
        ksort($expected);
        $actual = [];
        foreach (DockerImages::getAll() as $image) {
            if ($image->isZTS() || version_compare($image->getPHPVersion(), '5.6') < 0) {
                continue;
            }
            if (!isset($actual[$image->getPHPVersion()])) {
                $actual[$image->getPHPVersion()] = ['rc' => $image->isRC(), 'os' => []];
            }
            $actual[$image->getPHPVersion()]['os'][] = $image->getOS();
        }
        foreach (DockerImages::getAbandonedOfficialImages() as $phpVersion => $osList) {
            $actual[$phpVersion]['os'] = array_merge($actual[$phpVersion]['os'], $osList);
        }
        foreach (array_keys($actual) as $phpVersion) {
            sort($actual[$phpVersion]['os']);
        }
        ksort($actual);
        self::assertSame($expected, $actual, 'The Docker images in ' . DockerImages::class . ' must be updated');
    }
}
