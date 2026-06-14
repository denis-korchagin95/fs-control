<?php

/*
 * This file is part of fs-control.
 *
 * (c) Denis Korchagin <denis.korchagin.1995@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Configuration\Configuration
 */
class ConfigurationExcludeGlobTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldExcludeDirsMatchingAGlobAnywhereUnderTheScanRoot(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addExcludeDirGlob('**/Doc');

        self::assertTrue($configuration->isPathExcludedByDir('/root/Doc'));            // zero-segment
        self::assertTrue($configuration->isPathExcludedByDir('/root/Foo/Bar/Doc'));    // any depth
        self::assertTrue($configuration->isPathExcludedByDir('/root/Foo/Doc/sub'));    // subtree
        self::assertFalse($configuration->isPathExcludedByDir('/root/Foo/DocX'));      // no partial
        self::assertFalse($configuration->isPathExcludedByDir('/root/Foo/Other'));
    }

    /**
     * @test
     */
    public function itShouldExcludeExactPathsMatchingAGlobButNotTheirSubtree(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addExcludePathGlob('**/Config');

        self::assertTrue($configuration->isPathExcluded('/root/Foo/Config'));
        self::assertFalse($configuration->isPathExcluded('/root/Foo/Config/sub'));     // no subtree
        self::assertFalse($configuration->isPathExcluded('/root/Foo/Other'));
    }

    /**
     * @test
     */
    public function itShouldKeepLiteralExcludesWorkingAlongsideGlobs(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addExcludeDir('/root/Foo/Legacy');
        $configuration->addExcludeDirGlob('**/Doc');

        self::assertTrue($configuration->isPathExcludedByDir('/root/Foo/Legacy'));
        self::assertTrue($configuration->isPathExcludedByDir('/root/Foo/Legacy/Old'));
        self::assertTrue($configuration->isPathExcludedByDir('/root/Bar/Doc'));
    }

    /**
     * @test
     */
    public function itShouldNotMatchGlobsForPathsOutsideAnyScanRoot(): void
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addPath('/root');
        $configuration->addExcludeDirGlob('**/Doc');

        self::assertFalse($configuration->isPathExcludedByDir('/other/Doc'));
    }
}
