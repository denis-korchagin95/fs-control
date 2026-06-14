<?php

declare(strict_types=1);

namespace FsControl\Extension;

use FsControl\Core\Application;

/**
 * Optional capability an extension may implement to take part in the baseline workflow
 * (--generate-baseline / --baseline / --fail-on-stale-baseline). The core ExtensionInterface
 * is unaffected; extensions that do not implement this interface keep their current behavior.
 */
interface BaselineAwareExtension
{
    /**
     * Enumerate this extension's current findings as baseline entries.
     *
     * Keys are namespaced categories ("<extensionKey>:<subCategory>") and values are stable,
     * project-relative identities. The same identities must be used when the extension checks
     * Application::isFindingBaselined() so generation and filtering stay in sync.
     *
     * @return array<string, list<string>>
     */
    public function collectBaselineFindings(Application $application): array;
}
