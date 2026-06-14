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
use FsControl\Baseline\BaselineFilter;
use FsControl\Baseline\PathNormalizer;
use FsControl\Core\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Baseline\BaselineFilter
 * @covers \FsControl\Baseline\FilteredResult
 * @covers \FsControl\Baseline\Baseline
 * @covers \FsControl\Baseline\PathNormalizer
 */
class BaselineFilterTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldRemoveBaselinedFindingsAndKeepTheRest(): void
    {
        $result = new Result();
        $result->addViolationPath('/root/Foo/Known', 'reason');
        $result->addViolationPath('/root/Foo/New', 'reason');
        $result->addUncoveredPath('/root/Bar/Known', 'description');
        $result->addUnboundedPath('/root/Baz/New', 'reason');
        $result->addAllowedPath('/root/Foo/Allowed', 'allowed');
        $result->addBoundedPath('/root/Foo', 'bounded');

        $baseline = Baseline::fromPaths(
            ['Foo/Known'],
            ['Bar/Known'],
            [],
        );

        $filter = new BaselineFilter($baseline, new PathNormalizer('/root'));
        $filtered = $filter->apply($result);
        $activeResult = $filtered->getResult();

        // baselined findings are dropped from the failing categories
        self::assertSame(
            [['path' => '/root/Foo/New', 'reason' => 'reason']],
            $activeResult->getViolationPaths(),
        );
        self::assertSame([], $activeResult->getUncoveredPaths());
        self::assertSame(
            [['path' => '/root/Baz/New', 'reason' => 'reason']],
            $activeResult->getUnboundedPaths(),
        );

        // non-failing categories are carried over unchanged
        self::assertSame(
            [['path' => '/root/Foo/Allowed', 'description' => 'allowed']],
            $activeResult->getAllowedPaths(),
        );
        self::assertSame(
            [['path' => '/root/Foo', 'description' => 'bounded']],
            $activeResult->getBoundedPaths(),
        );

        // the baselined findings are reported with their original absolute path
        self::assertSame(
            [
                ['path' => '/root/Foo/Known', 'category' => Baseline::CATEGORY_VIOLATION],
                ['path' => '/root/Bar/Known', 'category' => Baseline::CATEGORY_UNCOVERED],
            ],
            $filtered->getBaselinedPaths(),
        );
        self::assertSame(2, $filtered->getBaselinedPathCount());
    }

    /**
     * @test
     */
    public function itShouldReportBaselineEntriesWithoutAMatchAsStale(): void
    {
        $result = new Result();
        $result->addViolationPath('/root/Foo/Known', 'reason');

        $baseline = Baseline::fromPaths(
            ['Foo/Known', 'Foo/Resolved'],
            ['Bar/Resolved'],
            [],
        );

        $filter = new BaselineFilter($baseline, new PathNormalizer('/root'));
        $filtered = $filter->apply($result);

        self::assertTrue($filtered->hasStalePaths());
        self::assertSame(
            [
                ['path' => 'Foo/Resolved', 'category' => Baseline::CATEGORY_VIOLATION],
                ['path' => 'Bar/Resolved', 'category' => Baseline::CATEGORY_UNCOVERED],
            ],
            $filtered->getStalePaths(),
        );
    }

    /**
     * @test
     */
    public function itShouldReportNoStalePathsWhenEveryEntryMatches(): void
    {
        $result = new Result();
        $result->addViolationPath('/root/Foo/Known', 'reason');

        $baseline = Baseline::fromPaths(['Foo/Known'], [], []);

        $filter = new BaselineFilter($baseline, new PathNormalizer('/root'));
        $filtered = $filter->apply($result);

        self::assertFalse($filtered->hasStalePaths());
        self::assertSame([], $filtered->getStalePaths());
    }
}
