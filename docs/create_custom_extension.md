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
