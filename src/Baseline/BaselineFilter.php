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

namespace FsControl\Baseline;

use FsControl\Core\PathNormalizer;
use FsControl\Core\Result;

class BaselineFilter
{
    public function __construct(
        private readonly Baseline $baseline,
        private readonly PathNormalizer $normalizer,
    ) {
    }

    /**
     * Splits a result against the baseline: baselined findings are removed from the
     * failing categories of a fresh result, the rest is carried over unchanged.
     */
    public function apply(Result $result): FilteredResult
    {
        $filtered = new Result();

        foreach ($result->getAllowedPaths() as $allowedPath) {
            $filtered->addAllowedPath($allowedPath['path'], $allowedPath['description']);
        }
        foreach ($result->getBoundedPaths() as $boundedPath) {
            $filtered->addBoundedPath($boundedPath['path'], $boundedPath['description']);
        }
        foreach ($result->getExcludedPaths() as $excludedPath) {
            $filtered->addExcludedPath($excludedPath['path'], $excludedPath['description']);
        }
        foreach ($result->getExcludedDirs() as $excludedDir) {
            $filtered->addExcludedDir($excludedDir['path'], $excludedDir['description']);
        }

        /** @var array{path: string, category: string}[] $baselinedPaths */
        $baselinedPaths = [];
        /** @var array<string, array<string, true>> $matched */
        $matched = [];

        foreach ($result->getViolationPaths() as $violationPath) {
            $relativePath = $this->normalizer->toRelative($violationPath['path']);
            if ($this->baseline->has(Baseline::CATEGORY_VIOLATION, $relativePath)) {
                $baselinedPaths[] = ['path' => $violationPath['path'], 'category' => Baseline::CATEGORY_VIOLATION];
                $matched[Baseline::CATEGORY_VIOLATION][$relativePath] = true;
                continue;
            }
            $filtered->addViolationPath($violationPath['path'], $violationPath['reason']);
        }

        foreach ($result->getUncoveredPaths() as $uncoveredPath) {
            $relativePath = $this->normalizer->toRelative($uncoveredPath['path']);
            if ($this->baseline->has(Baseline::CATEGORY_UNCOVERED, $relativePath)) {
                $baselinedPaths[] = ['path' => $uncoveredPath['path'], 'category' => Baseline::CATEGORY_UNCOVERED];
                $matched[Baseline::CATEGORY_UNCOVERED][$relativePath] = true;
                continue;
            }
            $filtered->addUncoveredPath($uncoveredPath['path'], $uncoveredPath['description']);
        }

        foreach ($result->getUnboundedPaths() as $unboundedPath) {
            $relativePath = $this->normalizer->toRelative($unboundedPath['path']);
            if ($this->baseline->has(Baseline::CATEGORY_UNBOUNDED, $relativePath)) {
                $baselinedPaths[] = ['path' => $unboundedPath['path'], 'category' => Baseline::CATEGORY_UNBOUNDED];
                $matched[Baseline::CATEGORY_UNBOUNDED][$relativePath] = true;
                continue;
            }
            $filtered->addUnboundedPath($unboundedPath['path'], $unboundedPath['reason']);
        }

        // Only core categories are owned by this filter; extension categories are accounted
        // for separately by the extension baseline workflow.
        $coreCategories = [
            Baseline::CATEGORY_VIOLATION,
            Baseline::CATEGORY_UNCOVERED,
            Baseline::CATEGORY_UNBOUNDED,
        ];
        /** @var array{path: string, category: string}[] $stalePaths */
        $stalePaths = [];
        foreach ($coreCategories as $category) {
            foreach ($this->baseline->categoryValues($category) as $relativePath) {
                if (! isset($matched[$category][$relativePath])) {
                    $stalePaths[] = ['path' => $relativePath, 'category' => $category];
                }
            }
        }

        return new FilteredResult($filtered, $baselinedPaths, $stalePaths);
    }
}
