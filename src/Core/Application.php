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
use FsControl\Configuration\Rule;
use FsControl\Exception\ExtensionException;
use FsControl\Extension\ExtensionInterface;
use FsControl\Loader\DirectoryTreeLoader;

class Application
{
    /**
     * @var ExtensionInterface[]
     */
    private array $extensions = [];

    /**
     * @var array<string, mixed>
     */
    private array $extensionInfo = [];

    /**
     * @throws ExtensionException
     */
    public function __construct(
        private readonly DirectoryTreeLoader $directoryTreeLoader,
        private readonly Configuration $configuration,
    ) {
        $this->loadExtensions();
    }

    /**
     * @throws ExtensionException
     */
    public function run(): Result
    {
        $result = new Result();
        foreach ($this->configuration->getPaths() as $path) {
            $this->handleOnePath($path, $result);
        }
        return $result;
    }

    /**
     * @throws ExtensionException
     */
    private function handleOnePath(string $path, Result $result): void
    {
        foreach ($this->directoryTreeLoader->loadDirectoryTree($path) as $directoryPath) {
            if ($this->configuration->isPathExcluded($directoryPath)) {
                $result->addExcludedPath($directoryPath);
                continue;
            }
            $pathHandleContext = $this->preparePathHandleContext($path, $directoryPath);

            foreach ($this->extensions as $extension) {
                $extension->handle($this, $pathHandleContext);
            }

            if ($this->configuration->isPathBounded($pathHandleContext->relativePath)) {
                $result->addBoundedPath($directoryPath);
                continue;
            }
            if ($pathHandleContext->binding === null) {
                $result->addUnboundedPath($directoryPath);
                continue;
            }
            if ($pathHandleContext->rule === null) {
                if ($pathHandleContext->directoryName !== null) {
                    $directoryList = explode('/', $pathHandleContext->directoryName);
                    $ruleEntryCount = 0;
                    foreach ($directoryList as $directory) {
                        $rule = $this->configuration->findRuleByName($directory);
                        if ($rule !== null) {
                            ++$ruleEntryCount;
                        }
                    }
                    $parameters = $this->configuration->getParameters();
                    $denyNestedRules = $parameters['deny_nested_rules'] ?? false;
                    if ($denyNestedRules === true && $ruleEntryCount > 1) {
                        $result->addViolationPath($directoryPath);
                        continue;
                    }
                    $rule = $this->configuration->findRuleByName($directoryList[0]);
                    if ($rule !== null) {
                        $ruleAttributes = $this->getAttributesForRule($rule);
                        $allowedSubdirectoryLevel = $ruleAttributes['allowed_subdirectory_level'] ?? 0;
                        $treatExceedSubdirectoryLevelAsFault = $ruleAttributes
                            ['treat_exceed_subdirectory_level_as_fault'] ?? false;
                        if (count($directoryList) - 1 <= $allowedSubdirectoryLevel) {
                            $result->addAllowedPath($directoryPath);
                            continue;
                        } elseif ($treatExceedSubdirectoryLevelAsFault === true) {
                            $result->addViolationPath($directoryPath);
                            continue;
                        }
                    }
                }
                $result->addUncoveredPath($directoryPath);
                continue;
            }
            if (! $pathHandleContext->rule->hasGroup($pathHandleContext->binding->getGroup())) {
                $result->addViolationPath($directoryPath);
                continue;
            }
            $result->addAllowedPath($directoryPath);
        }
    }

    /**
     * @throws ExtensionException
     */
    private function loadExtensions(): void
    {
        foreach ($this->configuration->getExtensions() as $extension) {
            $extension = new $extension();
            if (! $extension instanceof ExtensionInterface) {
                throw new ExtensionException(
                    $extension::class,
                    'Unable to load unknown extension!'
                    . ' All extensions should implement interface ' . ExtensionInterface::class,
                );
            }
            $this->addExtension($extension);

            $extension->boot($this);
        }
    }

    private function addExtension(ExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    private function preparePathHandleContext(string $rootPath, string $directoryPath): PathHandleContext
    {
        $relativePath = ltrim(str_replace($rootPath, '', $directoryPath), DIRECTORY_SEPARATOR);
        $binding = $this->configuration->getBindingForPath($relativePath);
        $directoryName = null;
        if ($binding !== null) {
            $directoryName = str_replace($binding->getResolvedBindingPath(), '', $relativePath);
            $directoryName = ltrim($directoryName, DIRECTORY_SEPARATOR);
        }
        $rule = null;
        if ($directoryName !== null) {
            $rule = $this->configuration->findRuleByName($directoryName);
        }
        return new PathHandleContext(
            $rootPath,
            $directoryPath,
            $relativePath,
            $binding,
            $directoryName,
            $rule,
        );
    }

    public function setExtensionInfo(string $extensionId, mixed $info): void
    {
        $this->extensionInfo[$extensionId] = $info;
    }

    public function getExtensionInfo(string $extensionId): mixed
    {
        return $this->extensionInfo[$extensionId] ?? null;
    }

    /**
     * @param resource $stream
     */
    public function terminate($stream): bool
    {
        $isTerminateSucceed = true;
        foreach ($this->extensions as $extension) {
            $isExtensionTerminateSucceed = $extension->terminate($this, $stream);
            if (! $isExtensionTerminateSucceed) {
                $isTerminateSucceed = false;
            }
        }
        return $isTerminateSucceed;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getAttributesForRule(Rule $rule): array
    {
        return array_replace(
            $this->getConfiguration()->getDefaultRuleAttributes(),
            $rule->getAttributes(),
        );
    }
}
