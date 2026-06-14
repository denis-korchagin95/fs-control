<?php

declare(strict_types=1);

namespace FsControl\Configuration;

class BindingMatch
{
    public function __construct(
        public readonly Binding $binding,
        public readonly string $mountPath,
    ) {
    }
}
