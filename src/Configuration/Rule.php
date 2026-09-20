<?php

declare(strict_types=1);

namespace FsControl\Configuration;

use FsControl\Exception\WrongRuleException;

class Rule
{
    /**
     * @var array<string, scalar|null>
     */
    private array $attributes = [];

    /**
     * @param string[] $groups
     * @param string[] $allowedAliases directory names accepted as this rule, silently
     * @param string[] $deprecatedAliases directory names still accepted, but reported as deprecated
     *
     * @throws WrongRuleException
     */
    public function __construct(
        private readonly string $name,
        private readonly array $groups,
        private readonly array $allowedAliases = [],
        private readonly array $deprecatedAliases = [],
    ) {
        if (str_contains($this->name, DIRECTORY_SEPARATOR)) {
            throw new WrongRuleException('You cannot set a path as a rule name!');
        }
        foreach ([...$this->allowedAliases, ...$this->deprecatedAliases] as $alias) {
            if ($alias === '' || str_contains($alias, DIRECTORY_SEPARATOR)) {
                throw new WrongRuleException(
                    'The alias "' . $alias . '" of the rule "' . $this->name . '" must be a directory name!',
                );
            }
            if ($alias === $this->name) {
                throw new WrongRuleException(
                    'The rule "' . $this->name . '" cannot use its own name as an alias!',
                );
            }
        }
        foreach ($this->allowedAliases as $alias) {
            if (in_array($alias, $this->deprecatedAliases, true)) {
                throw new WrongRuleException(
                    'The alias "' . $alias . '" of the rule "' . $this->name
                    . '" cannot be allowed and deprecated at the same time!',
                );
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return string[]
     */
    public function getAllowedAliases(): array
    {
        return $this->allowedAliases;
    }

    /**
     * @return string[]
     */
    public function getDeprecatedAliases(): array
    {
        return $this->deprecatedAliases;
    }

    /**
     * All the directory names this rule answers to, its own name included.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return [$this->name, ...$this->allowedAliases, ...$this->deprecatedAliases];
    }

    public function isDeprecatedName(string $name): bool
    {
        return in_array($name, $this->deprecatedAliases, true);
    }

    public function hasGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    public function addAttribute(string $name, int|float|string|bool|null $value): void
    {
        $this->attributes[$name] = $value;
    }
}
