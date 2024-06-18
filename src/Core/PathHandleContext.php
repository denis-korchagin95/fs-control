<?php

/*
 * This file is part of fs-control.
 *
 * (c) Denis Korchagin <denis.korchagin.1995@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
