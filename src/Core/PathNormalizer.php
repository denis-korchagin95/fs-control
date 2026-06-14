<?php

declare(strict_types=1);

namespace FsControl\Core;

class PathNormalizer
{
    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    /**
     * Turns an absolute path into a path relative to the project root.
     * Paths that do not live under the root are returned unchanged.
     */
    public function toRelative(string $path): string
    {
        $prefix = $this->root . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }
        return $path;
    }
}
