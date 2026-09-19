<?php

declare(strict_types=1);

namespace FsControl\Exception;

use FsControl\Configuration\Configuration;

use function implode;

class ConfigurationModeException extends FsControlException
{
    public static function unknownMode(string $mode): self
    {
        return new self(
            'The unknown mode "' . $mode . '"! Expected one of: '
            . implode(', ', Configuration::MODES) . '.',
        );
    }
}
