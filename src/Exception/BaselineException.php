<?php

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
