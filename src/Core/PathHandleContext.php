<?php

namespace FsControl\Core;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Rule;

class PathHandleContext
{
    public function __construct(
        public readonly string $rootPath,
        public readonly string $path,
        public readonly string $relativePath,
        public readonly ?Binding $binding,
        public readonly ?string $directoryName,
        public readonly ?Rule $rule,
    ) {
    }
}
