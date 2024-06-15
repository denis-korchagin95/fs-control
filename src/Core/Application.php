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

namespace FsControl\Core;

use FsControl\Configuration\Configuration;
use FsControl\Loader\DirectoryTreeLoader;

class Application
{
    public function __construct(
        private readonly DirectoryTreeLoader $directoryTreeLoader,
    ) {
    }

    public function run(Configuration $configuration): Result
    {
        $result = new Result();
        foreach ($configuration->getPaths() as $path) {
            $this->handleOnePath($path, $configuration, $result);
        }
        return $result;
    }

    private function handleOnePath(string $path, Configuration $configuration, Result $result): void
    {
        foreach ($this->directoryTreeLoader->loadDirectoryTree($path) as $directoryPath) {
            if ($configuration->isPathExcluded($directoryPath)) {
                continue;
            }
            $relativeDirectoryPath = ltrim(
                str_replace($path, '', $directoryPath),
                DIRECTORY_SEPARATOR,
            );
            if ($configuration->isPathBounded($relativeDirectoryPath)) {
                $result->addBoundedPath($directoryPath);
                continue;
            }
            $binding = $configuration->getBindingForPath($relativeDirectoryPath);
            if ($binding === null) {
                $result->addUnboundedPath($directoryPath);
                continue;
            }
            $directoryName = ltrim(str_replace(
                $binding->getResolvedBindingPath(),
                '',
                $relativeDirectoryPath,
            ), DIRECTORY_SEPARATOR);
            $rule = $configuration->findRuleByName($directoryName);
            if ($rule === null) {
                $result->addUncoveredPath($directoryPath);
                continue;
            }
            if (! $rule->hasGroup($binding->getGroup())) {
                $result->addViolationPath($directoryPath);
                continue;
            }
            $result->addAllowedPath($directoryPath);
        }
    }
}
