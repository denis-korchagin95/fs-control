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

namespace FsControl\Configuration;

class Binding
{
    public function __construct(
        private readonly string $bindingPath,
        private readonly string $resolvedBindingPath,
        private readonly string $group,
    ) {
    }

    public function getBindingPath(): string
    {
        return $this->bindingPath;
    }

    public function getResolvedBindingPath(): string
    {
        return $this->resolvedBindingPath;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function isBoundedFor(string $path): bool
    {
        return $path === $this->resolvedBindingPath
            || str_contains($this->resolvedBindingPath, $path);
    }

    public function getId(): string
    {
        return $this->getBindingPath() . ':' . $this->getGroup();
    }
}
