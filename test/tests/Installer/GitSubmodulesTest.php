<?php

namespace IPE\Test\Installer;

use IPE\Extension\GitSource;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Filesystem\VolatileDirectory;
use IPE\Installer\BuildOptions;
use IPE\Installer\GitSubmodules;
use IPE\Installer\State;
use IPE\Logger;
use IPE\PHPInfo;
use IPE\Test\Support\PSRLogger;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class GitSubmodulesTest extends TestCase
{
    /**
     * @var \IPE\Test\Support\PSRLogger
     */
    private $log;

    /**
     * @return void
     */
    public function testDisabled()
    {
        $subject = $this->createSubject(M::mock(Filesystem::class), false);
        $resolved = self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc'));
        self::assertFalse($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), M::mock(Filesystem::class)));
        self::assertSame([], $this->log->loggedStuff);
    }

    /**
     * @return void
     */
    public function testNothingMissing()
    {
        $fs = self::createFilesystem('/pkg', ['README']);
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc'));
        self::assertFalse($subject->isGitNeeded([$resolved]));
        self::assertFalse($subject->isGitNeeded([new Resolved(null, '/pkg', '1.0', Resolved::ORIGIN_PECL)]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['debug'], array_column($this->log->loggedStuff, 'level'));
        self::assertStringEndsWith(' are already present: liblzf', $this->log->loggedStuff[0]['message']);
    }

    /**
     * @return void
     */
    public function testNoGitSource()
    {
        $fs = M::mock(Filesystem::class);
        $subject = $this->createSubject($fs);
        $resolved = new Resolved(null, '/pkg', '1.0', Resolved::ORIGIN_PECL);
        self::assertFalse($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['debug'], array_column($this->log->loggedStuff, 'level'));
        self::assertStringContainsString("its source code doesn't come from a local directory, a URL or a Composer package", $this->log->loggedStuff[0]['message']);
    }

    /**
     * @return void
     */
    public function testNoGitmodules()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/pkg/.gitmodules')->andReturn(false);
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc'));
        self::assertFalse($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['debug: No git submodules are declared in /pkg'], $this->getLoggedMessages());
    }

    /**
     * @return void
     */
    public function testFromRepository()
    {
        $fs = self::createFilesystem('/pkg');
        $fs->shouldReceive('createVolatileDirectory')->once()->andReturnUsing(static function () use ($fs) {
            return new VolatileDirectory('/vol', $fs);
        });
        $fs->shouldReceive('exists')->with('/vol/s1')->andReturn(false);
        $fs->shouldReceive('createDirectory')->once()->with('/vol/s1');
        foreach ([
            ['init', '-q', '/vol/s1'],
            ['-C', '/vol/s1', 'fetch', '-q', '--depth', '1', 'https://github.com/o/r.git', 'abc'],
            ['-C', '/vol/s1', '-c', 'advice.detachedHead=false', 'checkout', '-q', 'FETCH_HEAD'],
            ['-C', '/vol/s1', 'submodule', 'update', '-q', '--init', '--recursive', '--depth', '1'],
        ] as $arguments) {
            $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('git', $arguments)->withOutput(Command::OUTPUT_CAPTURE_ALL)))->andReturn([0, '']);
        }
        self::mockClonedSubmodule($fs, '/vol/s1/liblzf');
        $fs->shouldReceive('deleteDirectory')->once()->with('/pkg/liblzf');
        $fs->shouldReceive('copyDirectory')->once()->with('/vol/s1/liblzf', '/pkg/liblzf', true);
        $fs->shouldReceive('deleteDirectory')->with('/vol');
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc'));
        self::assertTrue($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['debug', 'info'], array_column($this->log->loggedStuff, 'level'));
    }

    /**
     * The archive may lack the parent directories of the submodules, and the .git files of the submodules must not be copied.
     *
     * @return void
     */
    public function testFromRepositoryNestedPath()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->with('/pkg/.gitmodules')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/pkg/.gitmodules')->andReturn("[submodule \"bar\"]\n\tpath = dependencies/foo/bar\n");
        $fs->shouldReceive('isDirectory')->with('/pkg/dependencies/foo/bar')->andReturn(false);
        $fs->shouldReceive('createVolatileDirectory')->once()->andReturnUsing(static function () use ($fs) {
            return new VolatileDirectory('/vol', $fs);
        });
        $fs->shouldReceive('exists')->with('/vol/s1')->andReturn(false);
        $fs->shouldReceive('createDirectory')->once()->with('/vol/s1');
        $fs->shouldReceive('run')->times(4)->andReturn([0, '']);
        // The submodule has its own submodule
        $fs->shouldReceive('isFile')->with('/vol/s1/dependencies/foo/bar/.git')->andReturn(true);
        $fs->shouldReceive('deleteFile')->once()->with('/vol/s1/dependencies/foo/bar/.git');
        $fs->shouldReceive('isFile')->with('/vol/s1/dependencies/foo/bar/.gitmodules')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/vol/s1/dependencies/foo/bar/.gitmodules')->andReturn("[submodule \"nested\"]\n\tpath = nested\n");
        $fs->shouldReceive('isDirectory')->with('/vol/s1/dependencies/foo/bar/nested')->andReturn(true);
        self::mockClonedSubmodule($fs, '/vol/s1/dependencies/foo/bar/nested');
        $fs->shouldReceive('createDirectory')->once()->ordered()->with('/pkg/dependencies/foo/bar');
        $fs->shouldReceive('deleteDirectory')->once()->ordered()->with('/pkg/dependencies/foo/bar');
        $fs->shouldReceive('copyDirectory')->once()->ordered()->with('/vol/s1/dependencies/foo/bar', '/pkg/dependencies/foo/bar', true);
        $fs->shouldReceive('deleteDirectory')->with('/vol');
        $subject = $this->createSubject($fs);
        $subject->fetchMissing(self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc')), new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['info'], array_column($this->log->loggedStuff, 'level'));
    }

    /**
     * @return void
     */
    public function testFromRepositoryWithAbbreviatedCommit()
    {
        $fs = self::createFilesystem('/pkg');
        $fs->shouldReceive('createVolatileDirectory')->once()->andReturnUsing(static function () use ($fs) {
            return new VolatileDirectory('/vol', $fs);
        });
        $fs->shouldReceive('exists')->with('/vol/s1')->andReturn(false);
        $fs->shouldReceive('createDirectory')->once()->with('/vol/s1');
        foreach ([
            [['init', '-q', '/vol/s1'], 0],
            [['-C', '/vol/s1', 'fetch', '-q', '--depth', '1', 'https://github.com/o/r.git', 'abc'], 128],
            [['-C', '/vol/s1', 'fetch', '-q', '--update-head-ok', 'https://github.com/o/r.git', '+refs/heads/*:refs/heads/*', '+refs/tags/*:refs/tags/*'], 0],
            [['-C', '/vol/s1', '-c', 'advice.detachedHead=false', 'checkout', '-q', 'abc'], 0],
            [['-C', '/vol/s1', 'submodule', 'update', '-q', '--init', '--recursive', '--depth', '1'], 0],
        ] as $call) {
            $fs->shouldReceive('run')->once()->ordered()->with(self::isCommand(Command::create('git', $call[0])->withOutput(Command::OUTPUT_CAPTURE_ALL)))->andReturn([$call[1], '']);
        }
        self::mockClonedSubmodule($fs, '/vol/s1/liblzf');
        $fs->shouldReceive('deleteDirectory')->once()->with('/pkg/liblzf');
        $fs->shouldReceive('copyDirectory')->once()->with('/vol/s1/liblzf', '/pkg/liblzf', true);
        $fs->shouldReceive('deleteDirectory')->with('/vol');
        $subject = $this->createSubject($fs);
        $subject->fetchMissing(self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc')), new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['info', 'debug'], array_column($this->log->loggedStuff, 'level'));
    }

    /**
     * @return void
     */
    public function testFetchFailure()
    {
        $fs = self::createFilesystem('/pkg');
        $fs->shouldReceive('createVolatileDirectory')->once()->andReturnUsing(static function () use ($fs) {
            return new VolatileDirectory('/vol', $fs);
        });
        $fs->shouldReceive('exists')->with('/vol/s1')->andReturn(false);
        $fs->shouldReceive('createDirectory')->once()->with('/vol/s1');
        $fs->shouldReceive('run')->once()->ordered()->andReturn([0, '']);
        $fs->shouldReceive('run')->once()->ordered()->andReturn([128, 'fatal: unable to access']);
        $fs->shouldReceive('run')->once()->ordered()->andReturn([128, 'fatal: unable to access']);
        $fs->shouldReceive('deleteDirectory')->with('/vol');
        $subject = $this->createSubject($fs);
        $subject->fetchMissing(self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc')), new State(M::mock(PHPInfo::class), $fs));
        $last = end($this->log->loggedStuff);
        self::assertIsArray($last);
        self::assertSame('warning', $last['level']);
        self::assertStringContainsString('fatal: unable to access', $last['message']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideWorkingTrees()
    {
        return [
            'git directory' => ['directory'],
            'git file of submodules and worktrees' => ['file'],
        ];
    }

    /**
     * @dataProvider provideWorkingTrees
     *
     * @param string $gitKind
     *
     * @return void
     */
    public function testLocalWorkingTree($gitKind)
    {
        $fs = self::createFilesystem('/work/repo');
        self::mockGit($fs, '/work/repo', $gitKind);
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('git', ['-c', 'safe.directory=/work/repo', '-C', '/work/repo', 'submodule', 'update', '--init', '--recursive', '--', 'liblzf'])->withOutput(Command::OUTPUT_CAPTURE_ALL)))->andReturn([0, '']);
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved(new GitSource('/work/repo'));
        self::assertTrue($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['debug', 'info'], array_column($this->log->loggedStuff, 'level'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideNotWorkingTrees()
    {
        return [
            'no .git' => ['none'],
            'git directory without HEAD' => ['directory-without-head'],
            'git directory without objects' => ['directory-without-objects'],
            'git directory without refs' => ['directory-without-refs'],
            '.git file without gitdir' => ['file-without-gitdir'],
        ];
    }

    /**
     * The parent directories may belong to another repository (for example, an archive extracted in an ignored directory of a project).
     *
     * @dataProvider provideNotWorkingTrees
     *
     * @param string $gitKind
     *
     * @return void
     */
    public function testUnknownRepository($gitKind)
    {
        $fs = self::createFilesystem('/x/ext');
        self::mockGit($fs, '/x/ext', $gitKind);
        $fs->shouldNotReceive('isDirectory')->with('/x/.git');
        $fs->shouldNotReceive('isFile')->with('/x/.git');
        $fs->shouldNotReceive('isFile')->with('/x/.gitmodules');
        $fs->shouldNotReceive('run');
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved(new GitSource('/x/ext'));
        self::assertFalse($subject->isGitNeeded([$resolved]));
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        self::assertSame(['warning'], array_column($this->log->loggedStuff, 'level'));
    }

    /**
     * The paths that point outside the root directory are ignored, without touching them.
     *
     * @return void
     */
    public function testInvalidPaths()
    {
        $invalidPaths = ['/', '/etc', 'C:\\', 'C:/x', '../foo', 'a/../../b', './x', 'a//b', ':(top)x', '"quoted"'];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->with('/pkg/.gitmodules')->andReturn(true);
        $fs->shouldReceive('readFile')->with('/pkg/.gitmodules')->andReturn(implode('', array_map(static function ($path) {
            return "[submodule \"x\"]\n\tpath = {$path}\r\n";
        }, array_merge($invalidPaths, ['lib/']))));
        // Only the valid path is inspected
        $fs->shouldReceive('isDirectory')->with('/pkg/lib')->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with('/pkg/lib')->andReturn(['lib.c']);
        $subject = $this->createSubject($fs);
        $resolved = self::createResolved((new GitSource('/pkg'))->withRemoteRepository('https://github.com/o/r.git', 'abc'));
        self::assertFalse($subject->isGitNeeded([$resolved]));
        self::assertSame([], $this->getLoggedMessages());
        $subject->fetchMissing($resolved, new State(M::mock(PHPInfo::class), $fs));
        $messages = $this->getLoggedMessages();
        self::assertCount(count($invalidPaths) + 1, $messages);
        foreach ($invalidPaths as $index => $path) {
            self::assertSame("warning: Ignoring the git submodule path '{$path}' of /pkg/.gitmodules: it must be a relative path inside that directory", $messages[$index]);
        }
        self::assertStringEndsWith(' are already present: lib', $messages[count($invalidPaths)]);
    }

    /**
     * Mock a submodule in the clone, with its .git file and without submodules.
     *
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param non-empty-string $path
     *
     * @return void
     */
    private static function mockClonedSubmodule($fs, $path)
    {
        $fs->shouldReceive('isFile')->with("{$path}/.git")->andReturn(true);
        $fs->shouldReceive('deleteFile')->once()->with("{$path}/.git");
        $fs->shouldReceive('isFile')->with("{$path}/.gitmodules")->andReturn(false);
    }

    /**
     * @param \IPE\Filesystem&\Mockery\MockInterface $fs
     * @param non-empty-string $root
     * @param string $kind 'none', 'directory', 'directory-without-...', 'file' or 'file-without-gitdir'
     *
     * @return void
     */
    private static function mockGit($fs, $root, $kind)
    {
        $git = "{$root}/.git";
        $isDirectory = strpos($kind, 'directory') === 0;
        $fs->shouldReceive('isDirectory')->with($git)->andReturn($isDirectory);
        if ($isDirectory) {
            $fs->shouldReceive('isFile')->with("{$git}/HEAD")->andReturn($kind !== 'directory-without-head');
            $fs->shouldReceive('isDirectory')->with("{$git}/objects")->andReturn($kind !== 'directory-without-objects');
            $fs->shouldReceive('isDirectory')->with("{$git}/refs")->andReturn($kind !== 'directory-without-refs');

            return;
        }
        $fs->shouldReceive('isFile')->with($git)->andReturn($kind !== 'none');
        $fs->shouldReceive('readFile')->with($git)->andReturn($kind === 'file' ? "gitdir: ../.git/modules/repo\n" : "not a git file\n");
    }

    /**
     * @return list<string> the logged messages, prefixed by their level
     *
     * @phpstan-impure
     */
    private function getLoggedMessages()
    {
        $result = [];
        foreach ($this->log->loggedStuff as $logged) {
            $result[] = "{$logged['level']}: {$logged['message']}";
        }

        return $result;
    }

    /**
     * @param bool $enabled
     *
     * @return \IPE\Installer\GitSubmodules
     */
    private function createSubject(Filesystem $fs, $enabled = true)
    {
        $this->log = new PSRLogger();

        return new GitSubmodules($fs, (new BuildOptions())->withFetchGitSubmodules($enabled), new Logger($this->log));
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    private static function createResolved(GitSource $gitSource)
    {
        return (new Resolved(null, '/pkg', '1.0', Resolved::ORIGIN_COMPOSER))->withGitSource($gitSource);
    }

    /**
     * Mock a filesystem where $root contains the liblzf submodule.
     *
     * @param non-empty-string $root
     * @param string[] $submoduleContents
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem($root, array $submoduleContents = [])
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->with("{$root}/.gitmodules")->andReturn(true);
        $fs->shouldReceive('readFile')->with("{$root}/.gitmodules")->andReturn("[submodule \"liblzf\"]\n\tpath = liblzf\n\turl = https://github.com/nemequ/liblzf.git\n");
        $fs->shouldReceive('isDirectory')->with("{$root}/liblzf")->andReturn(true);
        $fs->shouldReceive('listDirectoryContents')->with("{$root}/liblzf")->andReturn($submoduleContents);

        return $fs;
    }
}
