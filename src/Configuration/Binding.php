<?php

declare(strict_types=1);

namespace FsControl\Configuration;

class Binding
{
    public function __construct(
        private readonly string $rawBindingPath,
        private readonly string $relativeBindingPath,
        private readonly string $targetGroup,
    ) {
    }

    public function getRawBindingPath(): string
    {
        return $this->rawBindingPath;
    }

    public function getRelativeBindingPath(): string
    {
        return $this->relativeBindingPath;
    }

    public function getTargetGroup(): string
    {
        return $this->targetGroup;
    }

    public function isBoundedFor(string $path): bool
    {
        return $path === $this->relativeBindingPath
            || str_contains($this->relativeBindingPath, $path);
    }

    public function getId(): string
    {
        return $this->getRawBindingPath() . ':' . $this->getTargetGroup();
    }
}
