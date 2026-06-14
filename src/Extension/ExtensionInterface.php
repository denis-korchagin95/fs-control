<?php

namespace FsControl\Extension;

use FsControl\Core\Application;
use FsControl\Core\PathHandleContext;
use FsControl\Exception\ExtensionException;

interface ExtensionInterface
{
    /**
     * @throws ExtensionException
     */
    public function boot(Application $application): void;

    /**
     * @throws ExtensionException
     */
    public function handle(Application $application, PathHandleContext $context): void;

    /**
     * @param resource $stream
     */
    public function terminate(Application $application, $stream): bool;
}
