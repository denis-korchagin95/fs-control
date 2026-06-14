# Creating a custom extension

First, you need to create an Extension class and extend the interface src/Extension/ExtensionInterface.php

```php
<?php

declare(strict_types=1);

namespace YouCustomNamespace;

use FsControl\Core\Application;
use FsControl\Core\PathHandleContext;
use FsControl\Extension\ExtensionInterface;

class SomeNewExtension implements ExtensionInterface
{
    public function boot(Application $application): void
    {
        // access to configuration to parse specific extension parameters
        // $application->getConfiguration()->getRawConfiguration()

        // you can store information about an extension for the next phase
        // $application->setExtensionInfo(self::class, 'value');
    }

    public function handle(Application $application, PathHandleContext $context): void
    {
        // if you need parsed configuration info, you can use the next method
        // $info = $application->getExtensionInfo(self::class);

        // here you can handle the path one by one

        // you can store information about an extension for the next phase
        // $application->setExtensionInfo(self::class, 'value');
    }

    public function terminate(Application $application, $stream): bool
    {
        // if you extension decide that we have an error, you should return false
        
        // output any errors to the profile stream fwrite($stream, 'info');

        return true;
    }
}
```

After that, you can use your extension register it in the config:

```yaml
fs_control:
  extensions:
    - YouCustomNamespace\SomeNewExtension
  your_custom_config_part_for_extension:
    # some extension settings
  # rest of the config ...
```

## Taking part in the baseline (optional)

If your extension reports findings, it can opt into the [baseline](./usage.md#baseline)
workflow by additionally implementing `FsControl\Extension\BaselineAwareExtension`. The
core `ExtensionInterface` is unchanged, so this is fully optional.

```php
use FsControl\Extension\BaselineAwareExtension;

class SomeNewExtension implements ExtensionInterface, BaselineAwareExtension
{
    private const NOT_EXCLUDED = 'some_new_extension:not_excluded';

    // ... boot()/handle() ...

    public function collectBaselineFindings(Application $application): array
    {
        // Enumerate the CURRENT findings as stable, project-relative identities.
        // Keys are namespaced "<extensionKey>:<subCategory>".
        return [self::NOT_EXCLUDED => [$application->toProjectRelativePath($somePath)]];
    }

    public function terminate(Application $application, $stream): bool
    {
        // Skip findings already recorded in the baseline and report only the rest.
        if ($application->isFindingBaselined(self::NOT_EXCLUDED, $application->toProjectRelativePath($somePath))) {
            return true;
        }
        // ... report the finding ... return false;
    }
}
```

* `collectBaselineFindings()` is used by `--generate-baseline` (so your findings end up in
  the file) and by the stale/baselined accounting.
* `Application::isFindingBaselined()` lets `terminate()` suppress a baselined finding (and
  not fail the run for it).
* `Application::toProjectRelativePath()` produces the same project-relative identity the core
  uses, so generation and checking stay in sync.

Findings are stored under an `extensions:` subtree in the baseline file — see the
[baseline format](./usage.md#baseline).
