<?php

declare(strict_types=1);

namespace FsControl\Test\Feature;

use FsControl\Configuration\Configuration;
use FsControl\Core\Application;
use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Loader\DirectoryTreeLoader
 * @covers \FsControl\Configuration\Configuration
 */
class ShouldNotScanSpecificDirectoryTest extends TestCase
{
    /**
     * @test
     * @throws DuplicateConfigurationEntryException
     */
    public function itShouldNotScanDirectoryOfAGitRepository(): void
    {
        $fs = vfsStream::setup('example', 444, ['.git' => []]);

        $configuration = new Configuration();
        $configuration->addPath($fs->url());

        $application = new Application(
            new DirectoryTreeLoader(['.git']),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(0, $result->getUnboundedPathCount());
    }
}
