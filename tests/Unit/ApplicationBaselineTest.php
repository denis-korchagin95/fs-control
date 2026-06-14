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

use FsControl\Baseline\Baseline;
use FsControl\Configuration\Configuration;
use FsControl\Core\Application;
use FsControl\Core\PathNormalizer;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Baseline\Baseline
 * @covers \FsControl\Core\PathNormalizer
 * @covers \FsControl\Configuration\Configuration
 */
class ApplicationBaselineTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldQueryBaselineAndNormalizePathsWhenConfigured(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => "extensions:\n    some_extension:\n        not_excluded:\n            - Foo/View\n",
        ]);
        $baseline = Baseline::loadFromFile($fs->url() . '/baseline.yaml');

        $application = new Application(
            new DirectoryTreeLoader([]),
            new Configuration('test-config', []),
            $baseline,
            new PathNormalizer('/root'),
        );

        self::assertTrue($application->isFindingBaselined('some_extension:not_excluded', 'Foo/View'));
        self::assertFalse($application->isFindingBaselined('some_extension:not_excluded', 'Foo/Other'));
        self::assertSame('Foo/View', $application->toProjectRelativePath('/root/Foo/View'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldBeInertWithoutABaselineOrNormalizer(): void
    {
        $application = new Application(
            new DirectoryTreeLoader([]),
            new Configuration('test-config', []),
        );

        self::assertFalse($application->isFindingBaselined('some_extension:not_excluded', 'Foo/View'));
        self::assertSame('/abs/Foo/View', $application->toProjectRelativePath('/abs/Foo/View'));
        self::assertSame([], $application->collectExtensionBaselineFindings());
    }
}
