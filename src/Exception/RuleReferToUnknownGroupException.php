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

namespace FsControl\Exception;

use FsControl\Configuration\Rule;

class RuleReferToUnknownGroupException extends FsControlException
{
    public function __construct(Rule $rule, string $group)
    {
        parent::__construct(
            'The rule "' . $rule->getTargetDirectoryName() . '" refer to the unknown group "' . $group . '"!',
        );
    }
}
