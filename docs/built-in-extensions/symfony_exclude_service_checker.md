# Symfony Exclude Service Checker

> [!IMPORTANT]
> 
> To use this extension, the config should fully cover your project or its part of the config.
> This means there are no excluded, unbounded, or uncovered paths.

If you are using the Symfony framework in your project,
you can use this extension to help exclude non-service paths from config resources.
Additionally, it can identify broken and unnecessary paths
to assist in managing your configuration and container more efficiently.

To use this built-in extension, you need to add to your config next settings:

```yaml
fs_control:
  extensions:
    - FsControl\BuiltInExtension\SymfonyExcludeServiceChecker\Extension
  symfony_exclude_service_checker:
    configs:
      - /path/to/your/symfony_config
  # rest of the config ...
```

For this setting, it will be checked only the broken paths.
If you check the not excluded paths, you need to mark non-service rules in the rule attributes section.

```yaml
fs_control:
  extensions:
    - FsControl\BuiltInExtension\SymfonyExcludeServiceChecker\Extension
  symfony_exclude_service_checker:
    configs:
      - /path/to/your/symfony_config
  # rest of the config ...
  rule_attributes:
    View:
      symfony_service: false
```

The output may include extension's section something like this after that:

```output
Found violations for config: /path/to/your/symfony_config/services.yaml
   Section App\Container\:
       Not excluded paths:
           ./example-fs/Container/Infrastructure/View
       Broken paths:
           ../../../example-fs/Container/Application/View/
```

## Baseline

This extension takes part in the [baseline](../usage.md#baseline) workflow. Both finding
kinds can be suppressed: not-excluded paths (category `not_excluded`) and broken paths
(category `broken`). Run `--generate-baseline=FILE` to capture the current findings, then
`--baseline=FILE` to suppress them — only newly introduced findings will fail afterwards:

```yaml
extensions:
    symfony_exclude_service_checker:
        not_excluded:
            - example-fs/Container/Infrastructure/View
        broken:
            - "example-fs/Container/config/services.yaml::../../../example-fs/Container/Application/View/"
```
