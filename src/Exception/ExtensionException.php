<?php

declare(strict_types=1);

namespace FsControl\Exception;

class ExtensionException extends FsControlException
{
    /**
     * @param class-string $extensionClass
     */
    public function __construct(string $extensionClass, string $message)
    {
        parent::__construct($extensionClass . ': ' . $message);
    }
}
