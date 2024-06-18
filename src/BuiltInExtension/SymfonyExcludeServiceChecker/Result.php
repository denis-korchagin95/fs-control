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

class Result
{
    /**
     * @var NotExcludedPath[]
     */
    private array $paths = [];

    public function addPath(NotExcludedPath $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * @return array{package: ExcludePackage, paths: string[]}[]
     */
    public function getPathsGroupedByExcludePackage(): array
    {
        $result = [];
        foreach ($this->paths as $path) {
            $excludePackageHash = spl_object_hash($path->excludePackage);
            if (! array_key_exists($excludePackageHash, $result)) {
                $result[$excludePackageHash] = ['package' => $path->excludePackage, 'paths' => [$path->path]];
                continue;
            }
            $result[$excludePackageHash]['paths'][] = $path->path;
        }
        return array_values($result);
    }
}
