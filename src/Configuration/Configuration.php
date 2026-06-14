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

use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\RuleReferToUnknownGroupException;
use Webmozart\Glob\Glob;

use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

class Configuration
{
    /**
     * @var string[]
     */
    private array $paths = [];

    /**
     * @var string[]
     */
    private array $excludePaths = [];

    /**
     * @var string[]
     */
    private array $excludeDirs = [];

    /**
     * @var string[]
     */
    private array $excludePathGlobs = [];

    /**
     * @var string[]
     */
    private array $excludeDirGlobs = [];

    /**
     * @var string[]
     */
    private array $groups = [];

    /**
     * @var Binding[]
     */
    private array $bindings = [];

    /**
     * @var array<string, Rule>
     */
    private array $rules = [];

    /**
     * @var array<string, scalar|null>
     */
    private array $defaultRuleAttributes = [];

    /**
     * @var class-string[]
     */
    private array $extensions = [];

    /**
     * @var mixed[]
     */
    private array $rawConfiguration;

    /**
     * @var array<string, scalar|null>
     */
    private array $parameters = [];

    private string $configPath;

    /**
     * @param mixed[] $rawConfiguration
     */
    public function __construct(
        string $configPath,
        array $rawConfiguration,
    ) {
        $this->configPath = $configPath;
        $this->rawConfiguration = $rawConfiguration;
    }

    public function isPathBounded(string $path): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding->isBoundedFor($path)) {
                return true;
            }
        }
        $match = $this->getBindingForPath($path);
        return $match !== null && $match->mountPath === $path;
    }

    public function isPathExcluded(string $path): bool
    {
        if (in_array($path, $this->excludePaths, true)) {
            return true;
        }
        return $this->matchesGlob($path, $this->excludePathGlobs, false);
    }

    public function isPathExcludedByDir(string $path): bool
    {
        foreach ($this->excludeDirs as $dir) {
            if ($path === $dir || str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return $this->matchesGlob($path, $this->excludeDirGlobs, true);
    }

    /**
     * Matches a scanned path against exclude globs, anchored to the scan root it lives under.
     * When $includeSubtree is true a glob also matches everything nested under a matching dir.
     *
     * @param string[] $globs
     */
    private function matchesGlob(string $path, array $globs, bool $includeSubtree): bool
    {
        $relativePath = $this->toScanRelative($path);
        if ($relativePath === null) {
            return false;
        }
        foreach ($globs as $glob) {
            if (Glob::match('/' . $relativePath, '/' . $glob)) {
                return true;
            }
            if ($includeSubtree && Glob::match('/' . $relativePath, '/' . $glob . '/**')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the given path relative to the scan root ("paths" entry) that contains it,
     * or null when it lives under none of them.
     */
    private function toScanRelative(string $path): ?string
    {
        foreach ($this->paths as $root) {
            if ($path === $root) {
                return '';
            }
            $prefix = $root . DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }
        return null;
    }

    public function getBindingForPath(string $path): ?BindingMatch
    {
        $isRule = fn (string $segment): bool => $this->findRuleByName($segment) !== null;

        $best = null;
        $bestLength = -1;
        foreach ($this->bindings as $binding) {
            $mountPath = $binding->matchMountPoint($path, $isRule);
            if ($mountPath === null) {
                continue;
            }
            $length = strlen($mountPath);
            if (
                $length > $bestLength
                || (
                    $length === $bestLength
                    && $best !== null
                    && $binding->specificityRank() > $best->binding->specificityRank()
                )
            ) {
                $best = new BindingMatch($binding, $mountPath);
                $bestLength = $length;
            }
        }
        return $best;
    }

    /**
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    public function findRuleByName(string $name): ?Rule
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addPath(string $path): void
    {
        if (in_array($path, $this->paths, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated path "' . $path . '"!');
        }
        $this->paths[] = $path;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addExcludePath(string $path): void
    {
        if (in_array($path, $this->excludePaths, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated exclude path "' . $path . '"!');
        }
        $this->excludePaths[] = $path;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addExcludeDir(string $path): void
    {
        if (in_array($path, $this->excludeDirs, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated exclude dir "' . $path . '"!');
        }
        $this->excludeDirs[] = $path;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addExcludePathGlob(string $glob): void
    {
        if (in_array($glob, $this->excludePathGlobs, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated exclude path glob "' . $glob . '"!');
        }
        $this->excludePathGlobs[] = $glob;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addExcludeDirGlob(string $glob): void
    {
        if (in_array($glob, $this->excludeDirGlobs, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated exclude dir glob "' . $glob . '"!');
        }
        $this->excludeDirGlobs[] = $glob;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addGroup(string $group): void
    {
        if (in_array($group, $this->groups, true)) {
            throw new DuplicateConfigurationEntryException('The duplicated group "' . $group . '"!');
        }
        $this->groups[] = $group;
    }

    /**
     * @throws DuplicateConfigurationEntryException
     */
    public function addBinding(Binding $binding): void
    {
        $hash = crc32($binding->getId());
        if (array_key_exists($hash, $this->bindings)) {
            throw new DuplicateConfigurationEntryException(
                'The duplicate binding "' . $binding->getId() . '"!',
            );
        }
        $this->bindings[$hash] = $binding;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function hasBindingToGroup(string $group): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding->getGroup() === $group) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws RuleReferToUnknownGroupException
     */
    public function addRule(Rule $rule): void
    {
        foreach ($rule->getGroups() as $group) {
            if (! $this->hasGroup($group)) {
                throw new RuleReferToUnknownGroupException($rule, $group);
            }
        }
        $this->rules[$rule->getName()] = $rule;
    }

    private function hasGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    /**
     * @return string[]
     */
    public function getExcludePaths(): array
    {
        return array_values($this->excludePaths);
    }

    /**
     * @return string[]
     */
    public function getExcludeDirs(): array
    {
        return array_values($this->excludeDirs);
    }

    /**
     * @return string[]
     */
    public function getExcludePathGlobs(): array
    {
        return array_values($this->excludePathGlobs);
    }

    /**
     * @return string[]
     */
    public function getExcludeDirGlobs(): array
    {
        return array_values($this->excludeDirGlobs);
    }

    /**
     * @return Binding[]
     */
    public function getBindings(): array
    {
        return array_values($this->bindings);
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    public function addDefaultRuleAttribute(string $name, int|float|string|bool|null $value): void
    {
        $this->defaultRuleAttributes[$name] = $value;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getDefaultRuleAttributes(): array
    {
        return $this->defaultRuleAttributes;
    }

    /**
     * @param class-string $extension
     */
    public function addExtension(string $extension): void
    {
        if (in_array($extension, $this->extensions, true)) {
            return;
        }
        $this->extensions[] = $extension;
    }

    /**
     * @return class-string[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return mixed[]
     */
    public function getRawConfiguration(): array
    {
        return $this->rawConfiguration;
    }

    public function addParameter(string $name, float|bool|int|string|null $value): void
    {
        $this->parameters[$name] = $value;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getConfigName(): string
    {
        return basename($this->configPath);
    }
}
