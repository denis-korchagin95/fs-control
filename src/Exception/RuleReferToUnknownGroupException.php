<?php

declare(strict_types=1);

namespace FsControl\Exception;

use FsControl\Configuration\Rule;

class RuleReferToUnknownGroupException extends FsControlException
{
    public function __construct(Rule $rule, string $group)
    {
        parent::__construct(
            'The rule "' . $rule->getName() . '" refer to the unknown group "' . $group . '"!',
        );
    }
}
