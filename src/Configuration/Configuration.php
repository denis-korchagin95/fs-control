<?php

declare(strict_types=1);

namespace FsControl\Configuration;

use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\RuleReferToUnknownGroupException;

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
    private array $groups = [];

    /**
     * @var Binding[]
     */
    private array $bindings = [];

    /**
     * @var array<string, Rule>
     */
    private array $rules = [];

    public function isPathBounded(string $path): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding->isBoundedFor($path)) {
                return true;
            }
        }
        return false;
    }

    public function isPathExcluded(string $path): bool
    {
        return in_array($path, $this->excludePaths, true);
    }

    public function getBindingForPath(string $path): ?Binding
    {
        foreach ($this->bindings as $binding) {
            if (str_starts_with($path, $binding->getRelativeBindingPath() . DIRECTORY_SEPARATOR)) {
                return $binding;
            }
        }
        return null;
    }

    /**
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    public function findRuleForPath(string $directoryName): ?Rule
    {
        return $this->rules[$directoryName] ?? null;
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

    public function hasBindingToTargetGroup(string $targetGroup): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding->getTargetGroup() === $targetGroup) {
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
        foreach ($rule->getTargetGroups() as $targetGroup) {
            if (! $this->hasGroup($targetGroup)) {
                throw new RuleReferToUnknownGroupException($rule, $targetGroup);
            }
        }
        $this->rules[$rule->getTargetDirectoryName()] = $rule;
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
}
