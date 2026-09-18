<?php

declare(strict_types=1);

namespace FsControl\Configuration;

/**
 * Parsed result of a ".fs-control-ignore" file: glob patterns split by whether a match
 * ignores the matched directory together with its whole subtree (trailing "/") or just the
 * exact matched directory.
 */
class IgnoreRules
{
    /**
     * @param string[] $subtreeGlobs
     * @param string[] $exactGlobs
     */
    public function __construct(
        private readonly array $subtreeGlobs,
        private readonly array $exactGlobs,
    ) {
    }

    /**
     * @return string[]
     */
    public function getSubtreeGlobs(): array
    {
        return $this->subtreeGlobs;
    }

    /**
     * @return string[]
     */
    public function getExactGlobs(): array
    {
        return $this->exactGlobs;
    }
}
