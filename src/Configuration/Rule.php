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
     * @throws WrongRuleException
     */
    public function __construct(
        private readonly string $name,
        private readonly array $groups,
    ) {
        if (str_contains($this->name, DIRECTORY_SEPARATOR)) {
            throw new WrongRuleException('You cannot set a path as a rule name!');
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

    public function hasGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    public function addAttribute(string $name, int|float|string|bool|null $value): void
    {
        $this->attributes[$name] = $value;
    }
}
