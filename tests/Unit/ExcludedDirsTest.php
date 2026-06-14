<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Core\Application;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Core\Result
 * @covers \FsControl\Configuration\Configuration
 * @covers \FsControl\Loader\DirectoryTreeLoader
 */
class ExcludedDirsTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportOnlyTheExcludeDirRootNotItsDescendants(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Domain' => [
                    'Entity' => [],
                    'Legacy' => [
                        'Old' => [
                            'Deep' => [],
                        ],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addRule(new Rule('Entity', ['Domain']));
        $configuration->addExcludeDir($fs->url() . '/Domain/Legacy');

        $result = (new Application(new DirectoryTreeLoader([]), $configuration))->run();

        // only the configured root is reported, not Old / Old/Deep underneath it
        self::assertSame(
            [
                [
                    'path' => 'vfs://example/Domain/Legacy',
                    'description' =>
                        'The path was excluded from analysis by the dir in the config "test-config"',
                ],
            ],
            $result->getExcludedDirs(),
        );
        self::assertSame(1, $result->getExcludedDirCount());
        self::assertSame(0, $result->getExcludedPathCount());
    }
}
