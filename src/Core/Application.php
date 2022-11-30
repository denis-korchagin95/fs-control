<?php

declare(strict_types=1);

namespace FsControl\Core;

use FsControl\Configuration\Configuration;
use FsControl\Loader\DirectoryTreeLoader;

class Application
{
    public function __construct(
        private readonly DirectoryTreeLoader $directoryTreeLoader,
        private readonly Configuration $configuration,
    ) {
    }

    public function run(): Result
    {
        $result = new Result();
        foreach ($this->configuration->getPaths() as $path) {
            $this->handleOnePath($path, $result);
        }
        return $result;
    }

    private function handleOnePath(string $path, Result $result): void
    {
        foreach ($this->directoryTreeLoader->loadDirectoryTree($path) as $directoryPath) {
            if ($this->configuration->isPathExcluded($directoryPath)) {
                continue;
            }
            $relativeDirectoryPath = ltrim(
                str_replace($path, '', $directoryPath),
                DIRECTORY_SEPARATOR,
            );
            if ($this->configuration->isPathBounded($relativeDirectoryPath)) {
                $result->addBoundedPath($directoryPath);
                continue;
            }
            $binding = $this->configuration->getBindingForPath($relativeDirectoryPath);
            if ($binding === null) {
                $result->addUnboundedPath($directoryPath);
                continue;
            }
            $targetDirectoryName = ltrim(str_replace(
                $binding->getRelativeBindingPath(),
                '',
                $relativeDirectoryPath,
            ), DIRECTORY_SEPARATOR);
            $rule = $this->configuration->findRuleForPath($targetDirectoryName);
            if ($rule === null) {
                $result->addUncoveredPath($directoryPath);
                continue;
            }
            if (! $rule->hasTargetGroup($binding->getTargetGroup())) {
                $result->addViolationPath($directoryPath);
                continue;
            }
            $result->addAllowedPath($directoryPath);
        }
    }
}
