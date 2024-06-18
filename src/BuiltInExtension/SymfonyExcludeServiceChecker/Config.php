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
