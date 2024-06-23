# Config Reference

> [!IMPORTANT]
> 
> This page about all the configuration options fs-control has.
> If you want to learn how to start using the tool,
> see [Getting started](https://github.com/denis-korchagin95/fs-control/tree/master?tab=readme-ov-file#getting-started)
> instead.

## YAML format

The fs-control uses configuration in famous YAML format.
If you aren't familiar with it, you can see [the YAML language specification](https://yaml.org/spec/1.2.2/).

This is how it can look like a simple config file:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

## Config file

A config file should be passed to `fs-control` as a main argument.

```console
./vendor/bin/fs-control example-fs-config.yaml
```

You can look at example config `example-fs-config.yaml` and apply the recommended settings to your project.

## Multiple paths

The `fs-control` don't state the philosophy about 1 config per 1 logic project part.
You can use it to control multiple paths if you have a similar structure to handle it.

To set up multiple paths, place another root directory in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
    - ./example-fs/Book
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

## Excluding paths

To exclude some paths from analysis, place a few of them in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  exclude_paths:
    - ./example-fs/Shared/Infrastructure/ParamConverter/Check
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

Typically, you can use the exclude path feature to avoid project restructuring when you first tune the `fs-control`,
or to ignore errors until you are ready to fix them.

## Parameters

Parameters can be used by built-in `fs-control` functions or its extensions in the section `parameters`.

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

Also, you can always to check the excluded paths
in the tool output using the flag `--show-excluded-paths` for some reason.

### Built-in parameters

`deny_nested_rules` (boolean) - It is used as a heuristic rule to help keep
the structure more linear and avoid deep nesting.

## Rule Attributes

Just like parameters, rule attributes can be built-in or supported by extensions under section `rule_attributes`.

An attribute can be set up for specific rule, for example, in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    Permission:
      symfony_service: false
  rules:
    Permission:
      - Application
```

Or if you want to assign the attributes for all rules, you can set it up under `_defaults` rule:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    _defaults:
      allow_subdirectory_level: 2
  rules:
    Permission:
      - Application
```

### Built-in rule attributes

* `allow_subdirectory_level` (integer, min: 0) - Allows controlling nesting depth by either expanding
or narrowing it for specific rules, or by allowing only a fixed nesting depth in the _defaults section.
* `treat_exceed_subdirectory_level_as_fault` (boolean) - Allows interpreting exceeding the nesting limits set
in `allow_subdirectory_level` as an error. By default, such a path will be considered uncovered.

## Extensions

To use extensions, they need to be declared in the extensions section:

```yaml
fs_control:
  extensions:
    - FsControl\BuiltInExtension\SymfonyExcludeServiceChecker\Extension
  symfony_exclude_service_checker:
    configs:
      - /path/to/your/symfony_config
  paths:
    - ./example-fs/Shared
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    Permission:
      symfony_service: false
  rules:
    Permission:
      - Application
```

If they provide additional configuration, fill it out similarly and adjust the attributes for the rules as needed.

You can see the list of [built-in extensions](./built_in_extensions.md)
or [create a custom extension](./create_custom_extension.md) for the tool.
