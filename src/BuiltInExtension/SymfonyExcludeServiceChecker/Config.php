<?php

declare(strict_types=1);

namespace FsControl\BuiltInExtension\SymfonyExcludeServiceChecker;

class Config
{
    /**
     * @var ExcludePackage[]
     */
    private array $excludePackages = [];

    public function addExcludePackage(ExcludePackage $excludePackage): void
    {
        $this->excludePackages[] = $excludePackage;
    }

    /**
     * @return ExcludePackage[]
     */
    public function getExcludePackages(): array
    {
        return $this->excludePackages;
    }

    public function findExcludePackageByResourcePath(string $path): ?ExcludePackage
    {
        foreach ($this->excludePackages as $excludePackage) {
            if ($excludePackage->resourcePath === $path) {
                return $excludePackage;
            }
        }
        return null;
    }
}
