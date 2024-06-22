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

        $configuration = new Configuration($filePath, $rawConfiguration);

        $this->resolvePaths($configuration, $rawConfiguration['fs_control']['paths'] ?? []);
        $this->resolveExcludePaths($configuration, $rawConfiguration['fs_control']['exclude_paths'] ?? []);
        $this->resolveGroups($configuration, $rawConfiguration['fs_control']['groups'] ?? []);
        $this->resolveBindings($configuration, $rawConfiguration['fs_control']['bindings'] ?? []);
        $this->resolveRules($configuration, $rawConfiguration['fs_control']['rules'] ?? []);
        $this->resolveRuleAttributes($configuration, $rawConfiguration['fs_control']['rule_attributes'] ?? []);
        $this->resolveExtensions($configuration, $rawConfiguration['fs_control']['extensions'] ?? []);
        $this->resolveParameters($configuration, $rawConfiguration['fs_control']['parameters'] ?? []);

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
     * @param string[] $paths
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     */
    private function resolvePaths(Configuration $configuration, array $paths): void
    {
        foreach ($paths as $path) {
            $resolvedPath = realpath($path);
            if ($resolvedPath === false) {
                throw new ConfigurationLoaderException('Can\'t resolve the path "' . $path . '"!');
            }
            $configuration->addPath($resolvedPath);
        }
    }

    /**
     * @param string[] $paths
     * @throws ConfigurationLoaderException
     * @throws DuplicateConfigurationEntryException
     */
    private function resolveExcludePaths(Configuration $configuration, array $paths): void
    {
        foreach ($paths as $path) {
            $resolvedPath = realpath($path);
            if ($resolvedPath === false) {
                throw new ConfigurationLoaderException('Can\'t resolve the path "' . $path . '"!');
            }
            $configuration->addExcludePath($resolvedPath);
        }
    }

    /**
     * @param string[] $groups
     * @throws DuplicateConfigurationEntryException
     */
    private function resolveGroups(Configuration $configuration, array $groups): void
    {
        foreach ($groups as $group => $options) {
            $configuration->addGroup($group);
        }
    }

    /**
     * @param array<string, string> $bindings
     * @throws DuplicateConfigurationEntryException
     */
    private function resolveBindings(Configuration $configuration, array $bindings): void
    {
        foreach ($bindings as $bindingPath => $group) {
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
     * @param array<string, string[]> $rules
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
     */
    private function resolveRules(Configuration $configuration, array $rules): void
    {
        foreach ($rules as $name => $groups) {
            $configuration->addRule(new Rule($name, $groups));
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
     * @param array<string, array<string, mixed>> $ruleAttributes
     *
     * @throws ConfigurationLoaderException
     */
    private function resolveRuleAttributes(Configuration $configuration, array $ruleAttributes): void
    {
        foreach ($ruleAttributes as $ruleName => $attributes) {
            if ($ruleName === '_defaults') {
                foreach ($attributes as $name => $value) {
                    if (! is_scalar($value) && ! is_null($value)) {
                        throw ConfigurationLoaderException::notScalarOrNullAttribute($name, $value);
                    }
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
                if (! is_scalar($value) && ! is_null($value)) {
                    throw ConfigurationLoaderException::notScalarOrNullAttribute($name, $value);
                }
                $this->tryToValidateBuiltInAttribute($ruleName, $name, $value);
                $rule->addAttribute($name, $value);
            }
        }
    }

    /**
     * @param class-string[] $extensions
     */
    private function resolveExtensions(Configuration $configuration, array $extensions): void
    {
        foreach ($extensions as $extension) {
            $configuration->addExtension($extension);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws ConfigurationLoaderException
     */
    private function resolveParameters(Configuration $configuration, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
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
