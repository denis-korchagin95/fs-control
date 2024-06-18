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

class ConfigurationLoaderException extends FsControlException
{
    public static function notScalarOrNullAttribute(string $attributeName, mixed $value): self
    {
        return new self(
            'The value of attribute "' . $attributeName
            . '" should be a scalar or null, but "' . get_debug_type($value) . '" given!',
        );
    }
}
