<?php

declare(strict_types=1);

namespace FsControl\Baseline;

use FsControl\Core\Result;

use function count;

class FilteredResult
{
    /**
     * @param array{path: string, category: string}[] $baselinedPaths
     * @param array{path: string, category: string}[] $stalePaths
     */
    public function __construct(
        private readonly Result $result,
        private readonly array $baselinedPaths,
        private readonly array $stalePaths,
    ) {
    }

    public function getResult(): Result
    {
        return $this->result;
    }

    /**
     * @return array{path: string, category: string}[]
     */
    public function getBaselinedPaths(): array
    {
        return $this->baselinedPaths;
    }

    /**
     * Baseline entries that did not match any finding in this run (resolved debt).
     *
     * @return array{path: string, category: string}[]
     */
    public function getStalePaths(): array
    {
        return $this->stalePaths;
    }

    public function getBaselinedPathCount(): int
    {
        return count($this->baselinedPaths);
    }

    public function hasStalePaths(): bool
    {
        return $this->stalePaths !== [];
    }
}
