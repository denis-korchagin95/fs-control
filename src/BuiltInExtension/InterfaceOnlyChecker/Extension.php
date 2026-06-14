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

namespace FsControl\BuiltInExtension\InterfaceOnlyChecker;

use FsControl\Core\Application;
use FsControl\Core\PathHandleContext;
use FsControl\Extension\BaselineAwareExtension;
use FsControl\Extension\ExtensionInterface;

use function array_map;
use function array_unique;
use function array_values;
use function basename;
use function explode;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function rtrim;
use function scandir;
use function sort;
use function str_ends_with;
use function token_get_all;

/**
 * Enforces that a rule's directory contains ONLY interfaces (no concrete class / trait /
 * enum), driven by the `interface_only` rule attribute — the same attribute-driven style as
 * the built-in SymfonyExcludeServiceChecker (`symfony_service`).
 *
 * Attribute value:
 *   - `true`            -> enforce wherever the rule is matched (any group)
 *   - "Domain"          -> enforce only when matched under the Domain group
 *   - "Domain,Contract" -> enforce under any of the listed groups
 *
 * Findings can be suppressed through the baseline (category
 * "interface_only_checker:not_interface", keyed per offending file).
 */
final class Extension implements ExtensionInterface, BaselineAwareExtension
{
    private const ATTRIBUTE = 'interface_only';
    private const EXTENSION_KEY = 'interface_only_checker';
    private const BASELINE_CATEGORY = self::EXTENSION_KEY . ':not_interface';
    private const INFO_VIOLATIONS = self::class . ':violations';

    /**
     * {@inheritDoc}
     */
    public function boot(Application $application): void
    {
        $application->setExtensionInfo(self::INFO_VIOLATIONS, []);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Application $application, PathHandleContext $context): void
    {
        $rule = $context->rule;
        if ($rule === null) {
            return;
        }
        $attributes = $application->getAttributesForRule($rule);
        $scope = $attributes[self::ATTRIBUTE] ?? null;
        if ($scope === null || $scope === false) {
            return;
        }
        if (! $this->scopeMatchesGroup($scope, $context->binding?->getGroup())) {
            return;
        }

        $offenders = [];
        foreach ($this->listPhpFiles($context->path) as $file) {
            if ($this->declaresConcreteType($file)) {
                $offenders[] = $file;
            }
        }
        if ($offenders === []) {
            return;
        }

        /** @var array<string, string[]> $violations */
        $violations = $application->getExtensionInfo(self::INFO_VIOLATIONS) ?? [];
        $violations[$context->path] = $offenders;
        $application->setExtensionInfo(self::INFO_VIOLATIONS, $violations);
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(Application $application, $stream): bool
    {
        $hasReported = false;
        foreach ($this->getViolations($application) as $directory => $files) {
            $offenders = [];
            foreach ($files as $file) {
                $identity = $application->toProjectRelativePath($file);
                if ($application->isFindingBaselined(self::BASELINE_CATEGORY, $identity)) {
                    continue;
                }
                $offenders[] = $file;
            }
            if ($offenders === []) {
                continue;
            }
            if (! $hasReported) {
                fwrite(
                    $stream,
                    PHP_EOL . 'Interface-only violations (concrete types in a contract-only rule directory):' . PHP_EOL,
                );
                $hasReported = true;
            }
            fwrite($stream, '  ' . $directory . PHP_EOL);
            foreach ($offenders as $file) {
                fwrite($stream, '      ' . basename($file) . ' is not an interface' . PHP_EOL);
            }
        }
        if (! $hasReported) {
            return true;
        }
        fwrite($stream, PHP_EOL);
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function collectBaselineFindings(Application $application): array
    {
        $identities = [];
        foreach ($this->getViolations($application) as $files) {
            foreach ($files as $file) {
                $identities[] = $application->toProjectRelativePath($file);
            }
        }
        if ($identities === []) {
            return [];
        }
        return [self::BASELINE_CATEGORY => array_values(array_unique($identities))];
    }

    /**
     * @return array<string, string[]> offending file lists keyed by rule directory
     */
    private function getViolations(Application $application): array
    {
        /** @var array<string, string[]> $violations */
        $violations = $application->getExtensionInfo(self::INFO_VIOLATIONS) ?? [];
        return $violations;
    }

    /**
     * Lists the *.php files directly inside a directory (non-recursive), sorted.
     * Uses scandir() rather than glob() so it works under disable_functions and on
     * stream wrappers.
     *
     * @return list<string>
     */
    private function listPhpFiles(string $directory): array
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }
        $files = [];
        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.php')) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    private function scopeMatchesGroup(bool|int|float|string $scope, ?string $group): bool
    {
        if ($scope === true) {
            return true;
        }
        if ($group === null) {
            return false;
        }
        $allowed = array_map('trim', explode(',', (string) $scope));
        return in_array($group, $allowed, true);
    }

    /**
     * True if the file declares a top-level class / trait / enum (a concrete type, not merely
     * an interface). Ignores `Foo::class` constants and anonymous `new class` expressions.
     */
    private function declaresConcreteType(string $file): bool
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return false;
        }
        $previous = null;
        foreach (token_get_all($code) as $token) {
            if (! is_array($token)) {
                $previous = $token;
                continue;
            }
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_TRAIT, T_ENUM], true)) {
                $isClassConstantOrAnonymous = $token[0] === T_CLASS
                    && is_array($previous)
                    && in_array($previous[0], [T_DOUBLE_COLON, T_NEW], true);
                if (! $isClassConstantOrAnonymous) {
                    return true;
                }
            }
            $previous = $token;
        }
        return false;
    }
}
