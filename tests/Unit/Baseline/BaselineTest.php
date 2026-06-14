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
use FsControl\Exception\BaselineException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Baseline\Baseline
 * @covers \FsControl\Exception\BaselineException
 */
class BaselineTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldLoadEntriesFromAFile(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => <<<YAML
            violations:
                - Foo/Application/Controller
            uncovered:
                - Bar/Domain/Widget
            YAML,
        ]);

        $baseline = Baseline::loadFromFile($fs->url() . '/baseline.yaml');

        self::assertTrue($baseline->has(Baseline::CATEGORY_VIOLATION, 'Foo/Application/Controller'));
        self::assertTrue($baseline->has(Baseline::CATEGORY_UNCOVERED, 'Bar/Domain/Widget'));
        self::assertFalse($baseline->has(Baseline::CATEGORY_VIOLATION, 'Bar/Domain/Widget'));
        self::assertFalse($baseline->has(Baseline::CATEGORY_UNBOUNDED, 'Foo/Application/Controller'));
    }

    /**
     * @test
     */
    public function itShouldExposeAllEntriesGroupedByCategory(): void
    {
        $baseline = Baseline::fromPaths(['Foo'], ['Bar'], ['Baz']);

        self::assertSame(
            [
                Baseline::CATEGORY_VIOLATION => ['Foo'],
                Baseline::CATEGORY_UNCOVERED => ['Bar'],
                Baseline::CATEGORY_UNBOUNDED => ['Baz'],
            ],
            $baseline->all(),
        );
    }

    /**
     * @test
     */
    public function itShouldLoadExtensionEntriesAsNamespacedCategories(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => <<<YAML
            violations:
                - Foo/Application/Controller
            extensions:
                some_extension:
                    not_excluded:
                        - Foo/Infrastructure/View
                    broken:
                        - "config/services.yaml::../bad/path"
            YAML,
        ]);

        $baseline = Baseline::loadFromFile($fs->url() . '/baseline.yaml');

        self::assertTrue($baseline->has('some_extension:not_excluded', 'Foo/Infrastructure/View'));
        self::assertTrue($baseline->has('some_extension:broken', 'config/services.yaml::../bad/path'));
        self::assertSame(
            ['some_extension:not_excluded', 'some_extension:broken'],
            $baseline->extensionCategories(),
        );
        self::assertSame(['Foo/Infrastructure/View'], $baseline->categoryValues('some_extension:not_excluded'));
        // core categories are not reported as extension categories
        self::assertFalse(in_array(Baseline::CATEGORY_VIOLATION, $baseline->extensionCategories(), true));
    }

    /**
     * @test
     */
    public function itShouldThrowWhenAnExtensionSectionIsNotAList(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => "extensions:\n    some_extension:\n        not_excluded: not-a-list\n",
        ]);

        $this->expectException(BaselineException::class);

        Baseline::loadFromFile($fs->url() . '/baseline.yaml');
    }

    /**
     * @test
     */
    public function itShouldThrowWhenTheFileDoesNotExist(): void
    {
        $this->expectException(BaselineException::class);

        Baseline::loadFromFile('vfs://example/missing.yaml');
    }

    /**
     * @test
     */
    public function itShouldThrowWhenTheRootIsNotAMapping(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => "- Foo\n- Bar\n",
        ]);

        $this->expectException(BaselineException::class);

        Baseline::loadFromFile($fs->url() . '/baseline.yaml');
    }

    /**
     * @test
     */
    public function itShouldThrowWhenASectionIsNotAList(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => "violations: not-a-list\n",
        ]);

        $this->expectException(BaselineException::class);

        Baseline::loadFromFile($fs->url() . '/baseline.yaml');
    }

    /**
     * @test
     */
    public function itShouldThrowWhenASectionContainsNonStringItems(): void
    {
        $fs = vfsStream::setup('example', null, [
            'baseline.yaml' => "violations:\n    - 42\n",
        ]);

        $this->expectException(BaselineException::class);

        Baseline::loadFromFile($fs->url() . '/baseline.yaml');
    }
}
