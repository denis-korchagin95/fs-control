<?php

declare(strict_types=1);

namespace FsControl\BuiltInExtension\SymfonyExcludeServiceChecker;

class NotExcludedPath
{
    public function __construct(
        public string $path,
        public ExcludePackage $excludePackage,
    ) {
    }
}
