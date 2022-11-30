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
