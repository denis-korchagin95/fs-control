<?php

declare(strict_types=1);

namespace FsControl\Configuration;

class Rule
{
    /**
     * @param string[] $targetGroups
     */
    public function __construct(
        private readonly string $targetDirectoryName,
        private readonly array $targetGroups,
    ) {
    }

    public function getTargetDirectoryName(): string
    {
        return $this->targetDirectoryName;
    }

    /**
     * @return string[]
     */
    public function getTargetGroups(): array
    {
        return $this->targetGroups;
    }

    public function hasTargetGroup(string $targetGroup): bool
    {
        return in_array($targetGroup, $this->targetGroups, true);
    }
}
