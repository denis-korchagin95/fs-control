<?php

declare(strict_types=1);

namespace FsControl\Test\Unit\Core;

use FsControl\Core\PathNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Core\PathNormalizer
 */
class PathNormalizerTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldStripTheProjectRootPrefix(): void
    {
        $normalizer = new PathNormalizer('/root');

        self::assertSame('Foo/Bar/Baz', $normalizer->toRelative('/root/Foo/Bar/Baz'));
    }

    /**
     * @test
     */
    public function itShouldTolerateATrailingSeparatorInTheRoot(): void
    {
        $normalizer = new PathNormalizer('/root/');

        self::assertSame('Foo/Bar', $normalizer->toRelative('/root/Foo/Bar'));
    }

    /**
     * @test
     */
    public function itShouldKeepPathsOutsideTheRootUnchanged(): void
    {
        $normalizer = new PathNormalizer('/root');

        self::assertSame('/other/Foo/Bar', $normalizer->toRelative('/other/Foo/Bar'));
    }
}
