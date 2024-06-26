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

namespace FsControl\BuiltInExtension\SymfonyExcludeServiceChecker;

use Webmozart\Glob\Glob;

class ExcludePackage
{
    /**
     * @param string[] $excludePaths
     * @param string[] $brokePaths
     */
    public function __construct(
        public readonly string $name,
        public readonly string $configPath,
        public readonly string $resourcePath,
        public readonly array $excludePaths,
        public readonly array $brokePaths,
    ) {
    }

    public function isPathExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $excludePath) {
            if (str_contains($excludePath, '*')) {
                if (Glob::match($path, $excludePath)) {
                    return true;
                } elseif (Glob::match($path . '/', $excludePath)) {
                    return true;
                }
                continue;
            }
            if ($excludePath === $path) {
                return true;
            }
        }
        return false;
    }

    public function hasViolations(): bool
    {
        return count($this->brokePaths) === 0;
    }
}
