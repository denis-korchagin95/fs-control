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

namespace FsControl\Test\Unit\Baseline;

use FsControl\Baseline\Baseline;
use FsControl\Baseline\BaselineWriter;
use FsControl\Baseline\PathNormalizer;
use FsControl\Core\Result;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Baseline\BaselineWriter
 * @covers \FsControl\Baseline\Baseline
 * @covers \FsControl\Baseline\PathNormalizer
 */
class BaselineWriterTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldGenerateSortedRelativePathsGroupedByCategory(): void
    {
        $result = new Result();
        $result->addViolationPath('/root/Foo/Zeta', 'reason');
        $result->addViolationPath('/root/Foo/Alpha', 'reason');
        $result->addUnboundedPath('/root/Bar/Widget', 'reason');

        $writer = new BaselineWriter(new PathNormalizer('/root'));

        self::assertSame(
            <<<YAML
            violations:
                - Foo/Alpha
                - Foo/Zeta
            unbounded:
                - Bar/Widget

            YAML,
            $writer->generate($result),
        );
    }

    /**
     * @test
     */
    public function itShouldGenerateAPlaceholderWhenThereAreNoFindings(): void
    {
        $writer = new BaselineWriter(new PathNormalizer('/root'));

        self::assertSame('# No findings to baseline.' . PHP_EOL, $writer->generate(new Result()));
    }

    /**
     * @test
     */
    public function itShouldWriteAFileThatLoadsBackIntoAnEquivalentBaseline(): void
    {
        $fs = vfsStream::setup('example');

        $result = new Result();
        $result->addViolationPath('/root/Foo/Known', 'reason');

        $writer = new BaselineWriter(new PathNormalizer('/root'));
        $writer->writeToFile($result, $fs->url() . '/baseline.yaml');

        $baseline = Baseline::loadFromFile($fs->url() . '/baseline.yaml');

        self::assertTrue($baseline->has(Baseline::CATEGORY_VIOLATION, 'Foo/Known'));
    }
}
