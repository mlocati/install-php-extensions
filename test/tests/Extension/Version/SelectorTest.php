<?php

namespace IPE\Test\Extension\Version;

use IPE\Exception;
use IPE\Extension\Version;
use IPE\Extension\Version\Selector;
use IPE\Extension\Version\Stability;
use IPE\Test\Support\TestCase;

final class SelectorTest extends TestCase
{
    /**
     * @return void
     */
    public function testIsExactVersion()
    {
        foreach (['1', '1.2.3', '1.0RC1', '10.21.0.11', '1.2.3-beta1'] as $exact) {
            self::assertTrue(Selector::isExactVersion($exact), $exact);
        }
        foreach (['', '*', '^1.2', '~1.2', '1.2.*', '>=1.1', '1.0 - 2.0', '1.0 || 2.0', '1.2@beta', 'stable'] as $notExact) {
            self::assertFalse(Selector::isExactVersion($notExact), $notExact);
        }
    }

    /**
     * @return array{string, string, string}[]
     */
    public static function provideParseRequirementsCases()
    {
        return [
            ['', '*', Stability::STABLE],
            ['  ', '*', Stability::STABLE],
            ['^1.2', '^1.2', Stability::STABLE],
            ['1.2.*', '1.2.*', Stability::STABLE],
            ['>=1.0 <2.0', '>=1.0 <2.0', Stability::STABLE],
            ['@beta', '*', Stability::BETA],
            ['@dev', '*', Stability::DEV],
            ['^1.2@RC', '^1.2', Stability::RC],
            ['^1.2@rc', '^1.2', Stability::RC],
            ['^1.2@alpha', '^1.2', Stability::ALPHA],
            ['^1.2@stable', '^1.2', Stability::STABLE],
            ['1.2.3', '1.2.3', Stability::DEV],
            ['1.2.3@beta', '1.2.3', Stability::BETA],
        ];
    }

    /**
     * @dataProvider provideParseRequirementsCases
     *
     * @param string $requirements
     * @param string $expectedConstraint
     * @param string $expectedStability
     *
     * @return void
     */
    public function testParseRequirements($requirements, $expectedConstraint, $expectedStability)
    {
        self::assertSame([$expectedConstraint, $expectedStability], Selector::parseRequirements($requirements));
    }

    /**
     * @return array{string}[]
     */
    public static function provideInvalidRequirementsCases()
    {
        return [
            ['foo bar'],
            ['^1.2@unstable'],
            ['>>1'],
        ];
    }

    /**
     * @dataProvider provideInvalidRequirementsCases
     *
     * @param string $requirements
     *
     * @return void
     */
    public function testInvalidRequirements($requirements)
    {
        try {
            Selector::parseRequirements($requirements);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString($requirements, $x->getMessage());
        }
    }

    /**
     * @return \IPE\Extension\Version[]
     */
    private static function getSampleVersions()
    {
        return [
            new Version\Pecl('foo', '1.0.0', 'stable'),
            new Version\Pecl('foo', '1.1.0', 'beta'),
            new Version\Pecl('foo', '1.0.1', 'stable'),
            new Version\Pecl('foo', '2.0.0RC1', 'beta'),
            new Version\Pecl('foo', '2.0.0', 'stable'),
            new Version\Pecl('foo', '0.9', 'stable'),
            new Version\Pecl('foo', '3.0.0', 'devel'),
            new Version\Pecl('foo', 'not-a-version', 'stable'),
        ];
    }

    /**
     * @return array{string, string}[]
     */
    public static function provideSelectCases()
    {
        return [
            ['', '2.0.0'],
            ['^1', '1.0.1'],
            ['^1@beta', '1.1.0'],
            ['~1.0.0', '1.0.1'],
            ['1.0.0', '1.0.0'],
            ['<1', '0.9'],
            ['@beta', '2.0.0'],
            ['@dev', '3.0.0'],
            ['^2@RC', '2.0.0'],
            ['2.0.0RC1@beta', '2.0.0RC1'],
            ['2.0.0RC1', '2.0.0RC1'],
            ['3.0.0', '3.0.0'],
            ['2.0.0RC1@stable', ''],
            ['^3', ''],
            ['^3@beta', ''],
        ];
    }

    /**
     * @dataProvider provideSelectCases
     *
     * @param string $requirements
     * @param string $expectedVersion empty string if no version should be selected
     *
     * @return void
     */
    public function testSelect($requirements, $expectedVersion)
    {
        $selector = new Selector(new \IPE\Logger());
        $selected = $selector->select(self::getSampleVersions(), $requirements);
        if ($expectedVersion === '') {
            self::assertNull($selected);
        } else {
            self::assertInstanceOf(Version::class, $selected);
            self::assertSame($expectedVersion, $selected->getVersion());
        }
    }

    /**
     * @return void
     */
    public function testSelectWithDefaultMinimumStability()
    {
        $selector = new Selector(new \IPE\Logger());
        $selected = $selector->select(self::getSampleVersions(), '', Stability::DEV);
        self::assertInstanceOf(Version::class, $selected);
        self::assertSame('3.0.0', $selected->getVersion());
        $selected = $selector->select(self::getSampleVersions(), '@stable', Stability::DEV);
        self::assertInstanceOf(Version::class, $selected);
        self::assertSame('2.0.0', $selected->getVersion());
    }

    /**
     * @return array{string, string}[]
     */
    public static function provideExplainNoMatchCases()
    {
        return [
            ['', ''],
            ['^4', 'no version satisfies the version requirements (the newest is 3.0.0 (dev))'],
            ['^3', 'the versions that satisfy the version requirements are less stable than stable (the newest is 3.0.0 (dev)): use @dev in the version requirements to accept it'],
            ['^3@beta', 'the versions that satisfy the version requirements are less stable than beta (the newest is 3.0.0 (dev)): use @dev in the version requirements to accept it'],
            ['2.0.0RC1@stable', 'the versions that satisfy the version requirements are less stable than stable (the newest is 2.0.0RC1 (beta)): use @beta in the version requirements to accept it'],
        ];
    }

    /**
     * @dataProvider provideExplainNoMatchCases
     *
     * @param string $requirements
     * @param string $expectedExplanation
     *
     * @return void
     */
    public function testExplainNoMatch($requirements, $expectedExplanation)
    {
        self::assertSame($expectedExplanation, (new Selector(new \IPE\Logger()))->explainNoMatch(self::getSampleVersions(), $requirements));
    }

    /**
     * @return void
     */
    public function testExplainNoMatchWithFewVersions()
    {
        $selector = new Selector(new \IPE\Logger());
        self::assertSame('no versions are available', $selector->explainNoMatch([], ''));
        self::assertSame(
            'all the versions are less stable than stable (the newest is 1.0.0RC1 (RC)): use @RC in the version requirements to accept it',
            $selector->explainNoMatch([new Version\Composer('1.0.0RC1', []), new Version\Composer('0.9.0-beta1', [])], '')
        );
    }
}
