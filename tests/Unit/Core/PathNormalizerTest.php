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
