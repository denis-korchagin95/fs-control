<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Configuration\Configuration
 */
class ConfigurationIgnoreTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldIgnoreSubtreeGlobsTogetherWithEverythingNested(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addIgnoreSubtreeGlob('**/Generated');

        self::assertTrue($configuration->isPathIgnored('/root/Generated'));            // any depth
        self::assertTrue($configuration->isPathIgnored('/root/Foo/Generated'));
        self::assertTrue($configuration->isPathIgnored('/root/Foo/Generated/deep/x')); // subtree
        self::assertFalse($configuration->isPathIgnored('/root/Foo/GeneratedX'));      // no partial
        self::assertFalse($configuration->isPathIgnored('/root/Foo/Other'));
    }

    /**
     * @test
     */
    public function itShouldIgnoreExactGlobsButNotTheirSubtree(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addIgnoreExactGlob('**/vendor');

        self::assertTrue($configuration->isPathIgnored('/root/vendor'));
        self::assertTrue($configuration->isPathIgnored('/root/Foo/vendor'));
        self::assertFalse($configuration->isPathIgnored('/root/vendor/sub'));     // no subtree
    }

    /**
     * @test
     */
    public function itShouldNotIgnorePathsOutsideAnyScanRoot(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addIgnoreSubtreeGlob('**/Generated');

        self::assertFalse($configuration->isPathIgnored('/other/Generated'));
    }

    /**
     * @test
     */
    public function itShouldRespectAnchoredIgnoreGlobsRelativeToTheScanRoot(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addIgnoreSubtreeGlob('src/Snapshots');

        self::assertTrue($configuration->isPathIgnored('/root/src/Snapshots'));
        self::assertTrue($configuration->isPathIgnored('/root/src/Snapshots/x'));
        self::assertFalse($configuration->isPathIgnored('/root/other/src/Snapshots'));
    }
}
