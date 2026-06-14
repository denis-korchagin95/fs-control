<?php

declare(strict_types=1);

namespace FsControl\Configuration;

use FsControl\Exception\ConfigurationLoaderException;

class Binding
{
    /**
     * @var list<array{type: 'lit'|'star'|'globstar', value: string}>
     */
    private array $pattern;

    /**
     * @throws ConfigurationLoaderException
     */
    public function __construct(
        private readonly string $bindingPath,
        private readonly string $resolvedBindingPath,
        private readonly string $group,
    ) {
        $this->pattern = $this->parsePattern($resolvedBindingPath);
    }

    public function getBindingPath(): string
    {
        return $this->bindingPath;
    }

    public function getResolvedBindingPath(): string
    {
        return $this->resolvedBindingPath;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function isBoundedFor(string $path): bool
    {
        return $path === $this->resolvedBindingPath
            || str_contains($this->resolvedBindingPath, $path);
    }

    public function getId(): string
    {
        return $this->getBindingPath() . ':' . $this->getGroup();
    }

    /**
     * Ranks how specific the binding is when two matches produce a mount of equal length.
     * A purely literal binding outranks a single "*", which outranks a greedy "**".
     */
    public function specificityRank(): int
    {
        $rank = 2;
        foreach ($this->pattern as $token) {
            if ($token['type'] === 'globstar') {
                return 0;
            }
            if ($token['type'] === 'star') {
                $rank = 1;
            }
        }
        return $rank;
    }

    /**
     * Resolves the concrete mount point for a path by consuming grouping segments
     * (greedily up to the first recognized rule for "**"). Returns the dynamic mount
     * prefix (e.g. "Layer/Group"), or null when the binding does not apply.
     *
     * @param callable(string): bool $isRule
     */
    public function matchMountPoint(string $relativePath, callable $isRule): ?string
    {
        $segments = $relativePath === '' ? [] : explode(DIRECTORY_SEPARATOR, $relativePath);
        $consumed = [];
        $index = 0;
        foreach ($this->pattern as $token) {
            if ($token['type'] === 'globstar') {
                while ($index < count($segments) && ! $isRule($segments[$index])) {
                    $consumed[] = $segments[$index];
                    ++$index;
                }
                continue;
            }
            if ($index >= count($segments)) {
                return null;
            }
            if ($token['type'] === 'lit' && $segments[$index] !== $token['value']) {
                return null;
            }
            $consumed[] = $segments[$index];
            ++$index;
        }
        return implode(DIRECTORY_SEPARATOR, $consumed);
    }

    /**
     * @return list<array{type: 'lit'|'star'|'globstar', value: string}>
     *
     * @throws ConfigurationLoaderException
     */
    private function parsePattern(string $resolvedBindingPath): array
    {
        $segments = $resolvedBindingPath === ''
            ? []
            : explode(DIRECTORY_SEPARATOR, $resolvedBindingPath);

        $pattern = [];
        $lastIndex = count($segments) - 1;
        foreach ($segments as $index => $segment) {
            $isLast = $index === $lastIndex;
            if ($segment === '**' || $segment === '*') {
                // a "*" / "**" wildcard is only allowed as the whole last segment
                if (! $isLast) {
                    throw ConfigurationLoaderException::invalidBindingPattern($this->bindingPath);
                }
                $pattern[] = $segment === '**'
                    ? ['type' => 'globstar', 'value' => $segment]
                    : ['type' => 'star', 'value' => $segment];
                continue;
            }
            // a literal segment must not embed a wildcard character (e.g. "Foo*", "a*b", "***")
            if (str_contains($segment, '*')) {
                throw ConfigurationLoaderException::invalidBindingPattern($this->bindingPath);
            }
            $pattern[] = ['type' => 'lit', 'value' => $segment];
        }

        return $pattern;
    }
}
