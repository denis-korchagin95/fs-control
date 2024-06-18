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
