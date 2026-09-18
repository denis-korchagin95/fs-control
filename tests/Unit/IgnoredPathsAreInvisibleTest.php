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
class IgnoredPathsAreInvisibleTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldNotReportIgnoredDirectoriesInAnyCategory(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Domain' => [
                    'Entity' => [],
                    'vendor' => [
                        'inner' => [],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addRule(new Rule('Entity', ['Domain']));
        $configuration->addIgnoreSubtreeGlob('**/vendor');

        $result = (new Application(new DirectoryTreeLoader([]), $configuration))->run();

        // the ignored "vendor" subtree appears in no category and is not counted anywhere,
        // unlike exclude_dirs which would be reported under "Excluded Dirs"
        self::assertSame(0, $result->getViolationPathCount());
        self::assertSame(0, $result->getUncoveredPathCount());
        self::assertSame(0, $result->getUnboundedPathCount());
        self::assertSame(0, $result->getExcludedDirCount());
        self::assertSame(0, $result->getExcludedPathCount());

        // only the real layer remains: Domain (mount point) and Domain/Entity (allowed)
        self::assertSame(1, $result->getBoundedPathCount());
        self::assertSame(
            ['vfs://example/Domain/Entity'],
            array_map(static fn (array $info): string => $info['path'], $result->getAllowedPaths()),
        );
    }
}
