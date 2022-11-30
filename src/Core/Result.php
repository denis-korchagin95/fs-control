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

namespace FsControl\Core;

class Result
{
    /**
     * @var string[]
     */
    private array $allowedPaths = [];

    /**
     * @var string[]
     */
    private array $boundedPaths = [];

    /**
     * @var string[]
     */
    private array $unboundedPaths = [];

    /**
     * @var string[]
     */
    private array $uncoveredPaths = [];

    /**
     * @var string[]
     */
    private array $violationPaths = [];

    public function addAllowedPath(string $allowedPath): void
    {
        $this->allowedPaths[] = $allowedPath;
    }

    public function addUnboundedPath(string $unboundedPath): void
    {
        $this->unboundedPaths[] = $unboundedPath;
    }

    public function addBoundedPath(string $boundedPath): void
    {
        $this->boundedPaths[] = $boundedPath;
    }

    public function addUncoveredPath(string $uncoveredPath): void
    {
        $this->uncoveredPaths[] = $uncoveredPath;
    }

    public function addViolationPath(string $violationPath): void
    {
        $this->violationPaths[] = $violationPath;
    }

    /**
     * @return string[]
     */
    public function getAllowedPaths(): array
    {
        return $this->allowedPaths;
    }

    /**
     * @return string[]
     */
    public function getBoundedPaths(): array
    {
        return $this->boundedPaths;
    }

    /**
     * @return string[]
     */
    public function getUnboundedPaths(): array
    {
        return $this->unboundedPaths;
    }

    /**
     * @return string[]
     */
    public function getUncoveredPaths(): array
    {
        return $this->uncoveredPaths;
    }

    /**
     * @return string[]
     */
    public function getViolationPaths(): array
    {
        return $this->violationPaths;
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
}
