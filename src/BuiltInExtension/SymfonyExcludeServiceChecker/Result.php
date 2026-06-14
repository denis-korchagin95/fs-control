<?php

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
