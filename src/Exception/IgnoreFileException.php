<?php

declare(strict_types=1);

namespace FsControl\Exception;

class IgnoreFileException extends FsControlException
{
    public static function unreadable(string $filePath): self
    {
        return new self('Can\'t read the ignore file "' . $filePath . '"!');
    }
}
