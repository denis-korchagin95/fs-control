<?php

declare(strict_types=1);

namespace FsControl\Test\Unit\Baseline;

use FsControl\Baseline\Baseline;
use FsControl\Baseline\BaselineWriter;
use FsControl\Core\PathNormalizer;
use FsControl\Core\Result;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Baseline\BaselineWriter
 * @covers \FsControl\Baseline\Baseline
 * @covers \FsControl\Core\PathNormalizer
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

    /**
     * @test
     */
    public function itShouldGenerateANestedExtensionsSubtree(): void
    {
        $result = new Result();
        $result->addViolationPath('/root/Foo/Known', 'reason');

        $writer = new BaselineWriter(new PathNormalizer('/root'));

        self::assertSame(
            <<<YAML
            violations:
                - Foo/Known
            extensions:
                some_extension:
                    broken:
                        - 'config/services.yaml::../bad'
                    not_excluded:
                        - Foo/Beta
                        - Foo/Zeta

            YAML,
            $writer->generate($result, [
                'some_extension:not_excluded' => ['Foo/Zeta', 'Foo/Beta'],
                'some_extension:broken' => ['config/services.yaml::../bad'],
            ]),
        );
    }

    /**
     * @test
     */
    public function itShouldWriteExtensionFindingsThatLoadBack(): void
    {
        $fs = vfsStream::setup('example');

        $writer = new BaselineWriter(new PathNormalizer('/root'));
        $writer->writeToFile(new Result(), $fs->url() . '/baseline.yaml', [
            'some_extension:not_excluded' => ['Foo/View'],
        ]);

        $baseline = Baseline::loadFromFile($fs->url() . '/baseline.yaml');

        self::assertTrue($baseline->has('some_extension:not_excluded', 'Foo/View'));
    }
}
