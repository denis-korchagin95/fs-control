<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Configuration;
use FsControl\Core\Application;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Loader\DirectoryTreeLoader
 * @covers \FsControl\Configuration\Configuration
 */
class ShouldNotScanSpecificDirectoryTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldNotScanDirectoryOfAGitRepository(): void
    {
        $fs = vfsStream::setup('example', 444, ['.git' => []]);

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());

        $application = new Application(
            new DirectoryTreeLoader(['.git']),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(0, $result->getUnboundedPathCount());
    }
}
