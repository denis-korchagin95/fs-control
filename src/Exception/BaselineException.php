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

class BaselineException extends FsControlException
{
    public static function cannotReadFile(string $path): self
    {
        return new self('The baseline file "' . $path . '" does not exist or is not readable!');
    }

    public static function malformed(string $path, string $reason): self
    {
        return new self('The baseline file "' . $path . '" is malformed: ' . $reason . '!');
    }

    public static function cannotWriteFile(string $path): self
    {
        return new self('Cannot write the baseline file "' . $path . '"!');
    }
}
