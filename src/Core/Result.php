<?php

declare(strict_types=1);

namespace FsControl\Core;

class Result
{
    /**
     * @var array{path: string, description: string}[]
     */
    private array $allowedPaths = [];

    /**
     * @var array{path: string, description: string}[]
     */
    private array $boundedPaths = [];

    /**
     * @var array{path: string, reason: string}[]
     */
    private array $unboundedPaths = [];

    /**
     * @var array{path: string, description: string}[]
     */
    private array $uncoveredPaths = [];

    /**
     * @var array{path: string, reason: string}[]
     */
    private array $violationPaths = [];

    /**
     * @var array{path: string, description: string}[]
     */
    private array $excludedPaths = [];

    /**
     * @var array{path: string, description: string}[]
     */
    private array $excludedDirs = [];

    public function addAllowedPath(string $path, string $description): void
    {
        $this->allowedPaths[] = ['path' => $path, 'description' => $description];
    }

    public function addUnboundedPath(string $path, string $reason): void
    {
        $this->unboundedPaths[] = ['path' => $path, 'reason' => $reason];
    }

    public function addBoundedPath(string $path, string $description): void
    {
        $this->boundedPaths[] = ['path' => $path, 'description' => $description];
    }

    public function addUncoveredPath(string $path, string $description): void
    {
        $this->uncoveredPaths[] = ['path' => $path, 'description' => $description];
    }

    public function addViolationPath(string $path, string $reason): void
    {
        $this->violationPaths[] = ['path' => $path, 'reason' => $reason];
    }

    public function addExcludedPath(string $path, string $description): void
    {
        $this->excludedPaths[] = ['path' => $path, 'description' => $description];
    }

    public function addExcludedDir(string $path, string $description): void
    {
        $this->excludedDirs[] = ['path' => $path, 'description' => $description];
    }

    /**
     * @return array{path: string, description: string}[]
     */
    public function getAllowedPaths(): array
    {
        return $this->allowedPaths;
    }

    /**
     * @return array{path: string, description: string}[]
     */
    public function getBoundedPaths(): array
    {
        return $this->boundedPaths;
    }

    /**
     * @return array{path: string, reason: string}[]
     */
    public function getUnboundedPaths(): array
    {
        return $this->unboundedPaths;
    }

    /**
     * @return array{path: string, description: string}[]
     */
    public function getUncoveredPaths(): array
    {
        return $this->uncoveredPaths;
    }

    /**
     * @return array{path: string, reason: string}[]
     */
    public function getViolationPaths(): array
    {
        return $this->violationPaths;
    }

    /**
     * @return array{path: string, description: string}[]
     */
    public function getExcludedPaths(): array
    {
        return $this->excludedPaths;
    }

    /**
     * @return array{path: string, description: string}[]
     */
    public function getExcludedDirs(): array
    {
        return $this->excludedDirs;
    }

    public function getViolationPathCount(): int
    {
        return count($this->violationPaths);
    }

    public function getUncoveredPathCount(): int
    {
        return count($this->uncoveredPaths);
    }

    public function getUnboundedPathCount(): int
    {
        return count($this->unboundedPaths);
    }

    public function getAllowedPathCount(): int
    {
        return count($this->allowedPaths);
    }

    public function getBoundedPathCount(): int
    {
        return count($this->boundedPaths);
    }

    public function getExcludedPathCount(): int
    {
        return count($this->excludedPaths);
    }

    public function getExcludedDirCount(): int
    {
        return count($this->excludedDirs);
    }

    public function hasViolationPaths(): bool
    {
        return $this->violationPaths !== [];
    }

    public function hasUncoveredPaths(): bool
    {
        return $this->uncoveredPaths !== [];
    }

    public function hasUnboundedPaths(): bool
    {
        return $this->unboundedPaths !== [];
    }

    public function hasExcludedPaths(): bool
    {
        return $this->excludedPaths !== [];
    }

    public function hasExcludedDirs(): bool
    {
        return $this->excludedDirs !== [];
    }

    public function hasAllowedPaths(): bool
    {
        return $this->allowedPaths !== [];
    }

    public function hasBoundedPaths(): bool
    {
        return $this->boundedPaths !== [];
    }
}
