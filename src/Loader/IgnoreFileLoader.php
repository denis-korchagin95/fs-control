<?php

declare(strict_types=1);

namespace FsControl\Loader;

use FsControl\Configuration\IgnoreRules;
use FsControl\Exception\IgnoreFileException;

use function ltrim;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Parses a ".fs-control-ignore" file (gitignore-flavored) into a set of glob patterns.
 *
 * Syntax (simplified, no negation):
 *  - blank lines and lines starting with "#" are ignored;
 *  - a trailing "/" ignores the matched directory and its whole subtree, otherwise only the
 *    exact matched directory is ignored;
 *  - a pattern without an internal "/" matches that name at any depth (prefixed with "**\/"),
 *    a pattern containing a "/" is anchored relative to the scan root.
 */
class IgnoreFileLoader
{
    /**
     * @throws IgnoreFileException
     */
    public function loadFromFile(string $filePath): IgnoreRules
    {
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw IgnoreFileException::unreadable($filePath);
        }

        $subtreeGlobs = [];
        $exactGlobs = [];
        foreach ($lines as $line) {
            $normalized = $this->normalize($line);
            if ($normalized === null) {
                continue;
            }
            [$glob, $isSubtree] = $normalized;
            if ($isSubtree) {
                $subtreeGlobs[] = $glob;
            } else {
                $exactGlobs[] = $glob;
            }
        }

        return new IgnoreRules($subtreeGlobs, $exactGlobs);
    }

    /**
     * Normalizes a single ignore line into a [glob, isSubtree] pair, or null when the line is
     * blank or a comment. isSubtree comes from a trailing "/".
     *
     * @return array{0: string, 1: bool}|null
     */
    private function normalize(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        $isSubtree = str_ends_with($line, '/');
        $core = rtrim($line, '/');
        if (str_starts_with($core, './')) {
            $core = substr($core, 2);
        }
        $anchored = str_contains($core, '/');
        $core = ltrim($core, '/');
        if ($core === '') {
            return null;
        }

        return [$anchored ? $core : '**/' . $core, $isSubtree];
    }
}
