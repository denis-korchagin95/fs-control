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

namespace FsControl\Loader;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Exception\ConfigurationLoaderException;
use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\RuleReferToUnknownGroupException;
use FsControl\Exception\WrongRuleException;
use Symfony\Component\Yaml\Yaml;

use function is_null;
use function is_scalar;

class ConfigurationLoader
{
    /**
     * @throws RuleReferToUnknownGroupException
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     * @throws WrongRuleException
     */
    public function loadFromFile(string $filePath): Configuration
    {
        $rawConfiguration = Yaml::parseFile($filePath);

        if (! is_array($rawConfiguration)) {
            throw new ConfigurationLoaderException('The configuration should be as array!');
        }

        if (! array_key_exists('fs_control', $rawConfiguration)) {
            throw new ConfigurationLoaderException('The root element must be "fs_control"!');
        }

        $rawFsControl = $rawConfiguration['fs_control'];
        if (! is_array($rawFsControl)) {
            throw new ConfigurationLoaderException('The "fs_control" element should be an array!');
        }

        $configuration = new Configuration($filePath, $rawConfiguration);

        $this->resolvePaths($configuration, $this->arraySection($rawFsControl, 'paths'));
        $this->resolveExcludePaths($configuration, $this->arraySection($rawFsControl, 'exclude_paths'));
        $this->resolveExcludeDirs($configuration, $this->arraySection($rawFsControl, 'exclude_dirs'));
        $this->resolveGroups($configuration, $this->arraySection($rawFsControl, 'groups'));
        $this->resolveBindings($configuration, $this->arraySection($rawFsControl, 'bindings'));
        $this->resolveRules($configuration, $this->arraySection($rawFsControl, 'rules'));
        $this->resolveRuleAttributes($configuration, $this->arraySection($rawFsControl, 'rule_attributes'));
        $this->resolveExtensions($configuration, $this->arraySection($rawFsControl, 'extensions'));
        $this->resolveParameters($configuration, $this->arraySection($rawFsControl, 'parameters'));

        foreach ($configuration->getGroups() as $group) {
            if (! $configuration->hasBindingToGroup($group)) {
                throw new ConfigurationLoaderException(
                    'Should be at least one binding for the group "' . $group . '"!',
                );
            }
        }

        return $configuration;
    }

    /**
     * Reads a top-level "fs_control" section as an array, defaulting to an empty array.
     *
     * @param array<mixed> $rawFsControl
     *
     * @return mixed[]
     *
     * @throws ConfigurationLoaderException
     */
    private function arraySection(array $rawFsControl, string $key): array
    {
        $value = $rawFsControl[$key] ?? [];
        if (! is_array($value)) {
            throw new ConfigurationLoaderException('The "' . $key . '" section should be an array!');
        }
        return $value;
    }

    /**
     * @param mixed[] $paths
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     */
    private function resolvePaths(Configuration $configuration, array $paths): void
    {
        $resolved = [];
        foreach ($paths as $path) {
            if (! is_string($path)) {
                throw new ConfigurationLoaderException('Each path should be a string!');
            }
            foreach ($this->resolvePathPattern($path) as $resolvedPath) {
                $resolved[$resolvedPath] = true;
            }
        }

        // Most-specific path wins: drop a resolved path when a deeper resolved path already
        // covers it, so a broad "./src/*" can coexist with deeper roots like "./src/Module/*"
        // without re-scanning the same directories twice.
        $all = array_keys($resolved);
        foreach ($all as $candidate) {
            $coveredByDeeper = false;
            foreach ($all as $other) {
                if ($other !== $candidate && str_starts_with($other, $candidate . DIRECTORY_SEPARATOR)) {
                    $coveredByDeeper = true;
                    break;
                }
            }
            if (! $coveredByDeeper) {
                $configuration->addPath($candidate);
            }
        }
    }

    /**
     * Resolves a single "paths" entry to one or more absolute directories. A literal path
     * resolves to itself; a path ending with a single "*" expands to its immediate
     * subdirectories, so sub-contexts can be covered without listing each one.
     *
     * @return string[]
     *
     * @throws ConfigurationLoaderException
     */
    private function resolvePathPattern(string $path): array
    {
        if (! str_contains($path, '*')) {
            $resolvedPath = realpath($path);
            if ($resolvedPath === false) {
                throw new ConfigurationLoaderException('Can\'t resolve the path "' . $path . '"!');
            }
            return [$resolvedPath];
        }

        $this->assertTailSingleStarPath($path);

        $resolvedParent = realpath(dirname($path));
        if ($resolvedParent === false) {
            throw new ConfigurationLoaderException('Can\'t resolve the path "' . $path . '"!');
        }

        $entries = scandir($resolvedParent);
        if ($entries === false) {
            return [];
        }

        $resolvedPaths = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, '.')) {
                continue;
            }
            $childPath = $resolvedParent . DIRECTORY_SEPARATOR . $entry;
            if (! is_dir($childPath)) {
                continue;
            }
            $resolvedChild = realpath($childPath);
            if ($resolvedChild === false) {
                continue;
            }
            $resolvedPaths[] = $resolvedChild;
        }
        sort($resolvedPaths);
        return $resolvedPaths;
    }

    /**
     * For "paths" only a single "*" as the whole last segment is allowed.
     *
     * @throws ConfigurationLoaderException
     */
    private function assertTailSingleStarPath(string $path): void
    {
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $lastIndex = count($segments) - 1;
        foreach ($segments as $index => $segment) {
            if ($segment === '*' && $index === $lastIndex) {
                continue;
            }
            if (str_contains($segment, '*')) {
                throw ConfigurationLoaderException::invalidPathPattern($path);
            }
        }
    }

    /**
     * @param mixed[] $paths
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     */
    private function resolveExcludePaths(Configuration $configuration, array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path)) {
                throw new ConfigurationLoaderException('Each exclude path should be a string!');
            }
            if (str_contains($path, '*')) {
                $configuration->addExcludePathGlob($path);
                continue;
            }
            $resolvedPath = realpath($path);
            if ($resolvedPath === false) {
                throw new ConfigurationLoaderException('Can\'t resolve the path "' . $path . '"!');
            }
            $configuration->addExcludePath($resolvedPath);
        }
    }

    /**
     * @param mixed[] $paths
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     */
    private function resolveExcludeDirs(Configuration $configuration, array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path)) {
                throw new ConfigurationLoaderException('Each exclude dir should be a string!');
            }
            if (str_contains($path, '*')) {
                $configuration->addExcludeDirGlob($path);
                continue;
            }
            $resolvedPath = realpath($path);
            if ($resolvedPath === false) {
                throw new ConfigurationLoaderException('Can\'t resolve the exclude dir "' . $path . '"!');
            }
            $configuration->addExcludeDir($resolvedPath);
        }
    }

    /**
     * @param mixed[] $groups
     * @throws DuplicateConfigurationEntryException
     * @throws ConfigurationLoaderException
     */
    private function resolveGroups(Configuration $configuration, array $groups): void
    {
        foreach ($groups as $group => $options) {
            if (! is_string($group)) {
                throw new ConfigurationLoaderException('Each group name should be a string!');
            }
            $configuration->addGroup($group);
        }
    }

    /**
     * @param mixed[] $bindings
     * @throws DuplicateConfigurationEntryException
     * @throws ConfigurationLoaderException
     */
    private function resolveBindings(Configuration $configuration, array $bindings): void
    {
        foreach ($bindings as $bindingPath => $group) {
            if (! is_string($bindingPath) || ! is_string($group)) {
                throw new ConfigurationLoaderException('Each binding must map a string path to a string group!');
            }
            if (! str_starts_with($bindingPath, '$')) {
                trigger_error(
                    'A binding path "' . $bindingPath . '" should start with "$"!',
                    E_USER_DEPRECATED,
                );
            }

            $configuration->addBinding(
                new Binding(
                    $bindingPath,
                    $this->resolveBindingPath($bindingPath),
                    $group,
                ),
            );
        }
    }

    /**
     * @param mixed[] $rules
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
     * @throws ConfigurationLoaderException
     */
    private function resolveRules(Configuration $configuration, array $rules): void
    {
        foreach ($rules as $name => $groups) {
            if (! is_string($name)) {
                throw new ConfigurationLoaderException('Each rule name should be a string!');
            }
            if (! is_array($groups)) {
                throw new ConfigurationLoaderException('The groups of rule "' . $name . '" should be a list!');
            }
            $ruleGroups = [];
            foreach ($groups as $group) {
                if (! is_string($group)) {
                    throw new ConfigurationLoaderException('The groups of rule "' . $name . '" must be strings!');
                }
                $ruleGroups[] = $group;
            }
            $configuration->addRule(new Rule($name, $ruleGroups));
        }
    }

    private function resolveBindingPath(string $bindingPath): string
    {
        $result = $bindingPath;
        if (str_contains($result, '$')) {
            $result = ltrim(
                str_replace('$', '', $result),
                DIRECTORY_SEPARATOR,
            );
        }
        return $result;
    }

    /**
     * @param mixed[] $ruleAttributes
     *
     * @throws ConfigurationLoaderException
     */
    private function resolveRuleAttributes(Configuration $configuration, array $ruleAttributes): void
    {
        foreach ($ruleAttributes as $ruleName => $attributes) {
            if (! is_string($ruleName)) {
                throw new ConfigurationLoaderException('Each rule attribute name should be a string!');
            }
            if (! is_array($attributes)) {
                throw new ConfigurationLoaderException(
                    'The attributes of rule "' . $ruleName . '" should be a mapping!',
                );
            }
            if ($ruleName === '_defaults') {
                foreach ($attributes as $name => $value) {
                    [$name, $value] = $this->assertAttribute($name, $value);
                    $this->tryToValidateBuiltInAttribute($ruleName, $name, $value);
                    $configuration->addDefaultRuleAttribute($name, $value);
                }
                continue;
            }
            $rule = $configuration->findRuleByName($ruleName);
            if ($rule === null) {
                throw new ConfigurationLoaderException(
                    'Rule "' . $ruleName . '" does not exist!',
                );
            }
            foreach ($attributes as $name => $value) {
                [$name, $value] = $this->assertAttribute($name, $value);
                $this->tryToValidateBuiltInAttribute($ruleName, $name, $value);
                $rule->addAttribute($name, $value);
            }
        }
    }

    /**
     * Validates a rule-attribute mapping entry (string name, scalar-or-null value).
     *
     * @param array-key $name
     *
     * @return array{0: string, 1: scalar|null}
     *
     * @throws ConfigurationLoaderException
     */
    private function assertAttribute(int|string $name, mixed $value): array
    {
        if (! is_string($name)) {
            throw new ConfigurationLoaderException('Each attribute name should be a string!');
        }
        if (! is_scalar($value) && ! is_null($value)) {
            throw ConfigurationLoaderException::notScalarOrNullAttribute($name, $value);
        }
        return [$name, $value];
    }

    /**
     * @param mixed[] $extensions
     *
     * @throws ConfigurationLoaderException
     */
    private function resolveExtensions(Configuration $configuration, array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (! is_string($extension) || ! class_exists($extension)) {
                throw new ConfigurationLoaderException(
                    'Each extension should be an existing class name!',
                );
            }
            $configuration->addExtension($extension);
        }
    }

    /**
     * @param mixed[] $parameters
     *
     * @throws ConfigurationLoaderException
     */
    private function resolveParameters(Configuration $configuration, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            if (! is_string($name)) {
                throw new ConfigurationLoaderException('Each parameter name should be a string!');
            }
            if (! is_scalar($value) && ! is_null($value)) {
                throw ConfigurationLoaderException::notScalarOrNullParameter($name, $value);
            }
            $this->tryToValidateBuiltInParameter($name, $value);
            $configuration->addParameter($name, $value);
        }
    }

    /**
     * @throws ConfigurationLoaderException
     */
    private function tryToValidateBuiltInAttribute(
        string $ruleName,
        string $attributeName,
        float|bool|int|string|null $value,
    ): void {
        if ($attributeName === 'allowed_subdirectory_level') {
            if (! is_int($value)) {
                throw new ConfigurationLoaderException(
                    'The attribute "allowed_subdirectory_level" for rule "'
                    . $ruleName . '" must be an integer!',
                );
            }
            if ($value < 0) {
                throw new ConfigurationLoaderException(
                    'The attribute "allowed_subdirectory_level" for rule "'
                    . $ruleName . '" should be greater than or equal to zero!',
                );
            }
        }
        if ($attributeName === 'treat_exceed_subdirectory_level_as_fault') {
            if (! is_bool($value)) {
                throw new ConfigurationLoaderException(
                    'The attribute "treat_exceed_subdirectory_level_as_fault" for rule "'
                    . $ruleName . '" must be a boolean!',
                );
            }
        }
    }

    /**
     * @throws ConfigurationLoaderException
     */
    private function tryToValidateBuiltInParameter(string $name, float|bool|int|string|null $value): void
    {
        if ($name === 'deny_nested_rules') {
            if (! is_bool($value)) {
                throw new ConfigurationLoaderException(
                    'The parameter "deny_nested_rules" must be a boolean!',
                );
            }
        }
    }
}
