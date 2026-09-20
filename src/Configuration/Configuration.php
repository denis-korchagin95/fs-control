<?php

declare(strict_types=1);

namespace FsControl\Configuration;

use FsControl\Exception\ConfigurationModeException;
use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\RuleReferToUnknownGroupException;
use Webmozart\Glob\Glob;

use function array_pop;
use function explode;
use function implode;
use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

class Configuration
{
    /**
     * Every path is judged against the rules: one that no binding or rule covers is reported
     * as unbounded/uncovered. This is the default, so an existing config keeps its behavior.
     */
    public const MODE_STRICT = 'strict';

    /**
     * The defined rules are still enforced — a covered path that breaks its rule is a violation
     * as usual — but a path that no binding or rule covers is reported as out of coverage
     * instead of unbounded/uncovered, and never fails the run.
     */
    public const MODE_TOLERANT = 'tolerant';

    public const MODES = [
        self::MODE_STRICT,
        self::MODE_TOLERANT,
    ];

    private string $mode = self::MODE_STRICT;

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
    private array $ignoreSubtreeGlobs = [];

    /**
     * @var string[]
     */
    private array $ignoreExactGlobs = [];

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
     * @var array<string, Rule>
     */
    private array $ruleAliases = [];

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

    /**
     * @throws ConfigurationModeException
     */
    public function setMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw ConfigurationModeException::unknownMode($mode);
        }
        $this->mode = $mode;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isTolerantMode(): bool
    {
        return $this->mode === self::MODE_TOLERANT;
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
     * Whether the path is ignored via the ".fs-control-ignore" file. Unlike exclude_dirs/paths,
     * an ignored path is fully invisible to the tool — it produces no finding in any category.
     * A subtree glob (trailing "/" in the ignore file) also ignores everything nested under a
     * matched directory; an exact glob ignores only the matched directory itself.
     */
    public function isPathIgnored(string $path): bool
    {
        return $this->matchesGlob($path, $this->ignoreSubtreeGlobs, true)
            || $this->matchesGlob($path, $this->ignoreExactGlobs, false);
    }

    /**
     * Whether the path is a directory directly listed in exclude_dirs (literal) or directly
     * matched by an exclude_dirs glob — i.e. a configured exclude root, not merely a descendant
     * swept up under one. Used to report only the introduced/expanded exclude dirs.
     */
    public function isExcludeDirRoot(string $path): bool
    {
        foreach ($this->excludeDirs as $dir) {
            if ($path === $dir) {
                return true;
            }
        }
        return $this->matchesGlob($path, $this->excludeDirGlobs, false);
    }

    /**
     * Matches a scanned path against exclude globs, anchored to the scan root it lives under.
     * When $includeSubtree is true a glob also matches everything nested (at any depth) under a
     * matching directory — checked by walking the path's ancestors, since a trailing "**" only
     * matches a single segment in webmozart/glob.
     *
     * @param string[] $globs
     */
    private function matchesGlob(string $path, array $globs, bool $includeSubtree): bool
    {
        foreach ($this->toScanRelativeCandidates($path) as $relativePath) {
            if ($this->relativePathMatchesGlob($relativePath, $globs)) {
                return true;
            }
            if (! $includeSubtree) {
                continue;
            }
            $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
            array_pop($segments);
            while ($segments !== []) {
                if ($this->relativePathMatchesGlob(implode(DIRECTORY_SEPARATOR, $segments), $globs)) {
                    return true;
                }
                array_pop($segments);
            }
        }
        return false;
    }

    /**
     * @param string[] $globs
     */
    private function relativePathMatchesGlob(string $relativePath, array $globs): bool
    {
        foreach ($globs as $glob) {
            if (Glob::match('/' . $relativePath, '/' . $glob)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ways the given path can be anchored for glob matching, or an empty list when
     * it lives under no scan root ("paths" entry).
     *
     * The first candidate is the path relative to its scan root. The second one keeps the scan
     * root's own name in front, so a glob naming that very directory reaches it as well: a
     * "paths" expansion like "./src/*" turns every sub-context into a scan root, and such a root
     * must stay excludable by "**\/Legacy" just like a "Legacy" nested deeper.
     *
     * @return string[]
     */
    private function toScanRelativeCandidates(string $path): array
    {
        foreach ($this->paths as $root) {
            $rootName = basename($root);
            if ($path === $root) {
                return [$rootName];
            }
            $prefix = $root . DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $prefix)) {
                $relativePath = substr($path, strlen($prefix));
                return [$relativePath, $rootName . DIRECTORY_SEPARATOR . $relativePath];
            }
        }
        return [];
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
        return $this->rules[$name] ?? $this->ruleAliases[$name] ?? null;
    }

    public function hasDeprecatedAliases(): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->getDeprecatedAliases() !== []) {
                return true;
            }
        }
        return false;
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

    public function addIgnoreSubtreeGlob(string $glob): void
    {
        if (! in_array($glob, $this->ignoreSubtreeGlobs, true)) {
            $this->ignoreSubtreeGlobs[] = $glob;
        }
    }

    public function addIgnoreExactGlob(string $glob): void
    {
        if (! in_array($glob, $this->ignoreExactGlobs, true)) {
            $this->ignoreExactGlobs[] = $glob;
        }
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
     * @throws DuplicateConfigurationEntryException
     */
    public function addRule(Rule $rule): void
    {
        foreach ($rule->getGroups() as $group) {
            if (! $this->hasGroup($group)) {
                throw new RuleReferToUnknownGroupException($rule, $group);
            }
        }
        foreach ($rule->getNames() as $name) {
            $owner = $this->findRuleByName($name);
            if ($owner !== null && $owner->getName() !== $rule->getName()) {
                throw new DuplicateConfigurationEntryException(
                    'The name "' . $name . '" of the rule "' . $rule->getName()
                    . '" is already used by the rule "' . $owner->getName() . '"!',
                );
            }
        }
        foreach ([...$rule->getAllowedAliases(), ...$rule->getDeprecatedAliases()] as $alias) {
            $this->ruleAliases[$alias] = $rule;
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
